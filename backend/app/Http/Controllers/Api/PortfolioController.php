<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PortfolioPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortfolioController extends Controller
{
    /**
     * Resolve the internship this student may build a portfolio for,
     * or fail with a clear, user-friendly message instead of a raw
     * ModelNotFoundException.
     */
    private function internship(Request $request)
    {
        $internship = $request->user()->portfolioInternship()->first();

        if (!$internship) {
            abort(response()->json([
                'message' => 'No active or completed internship found for your account. Please contact your coordinator if this is unexpected.'
            ], 404));
        }

        return $internship;
    }

    /**
     * GET /api/v1/student/portfolio
     * Fetch the portfolio data for the active internship.
     * Includes all related models needed for the PDF generation.
     */
    public function getPortfolio(Request $request)
    {
        $internship = $request->user()->portfolioInternship()
            ->with([
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'coordinator.facultyProfile',
                'portfolio.photos',
                'journals' => function ($q) {
                    $q->where('status', 'approved')->orderBy('week_number', 'asc')->orderBy('date', 'asc');
                },
                'attendance' => function ($q) {
                    $q->where('status', 'validated')->orderBy('date', 'asc');
                },
                'evaluations',
                'documents' => function ($q) {
                    $q->where('status', 'approved');
                }
            ])
            ->first();

        if (!$internship) {
            return response()->json(['message' => 'No active internship found'], 404);
        }

        // Auto-create an empty portfolio record if it doesn't exist
        if (!$internship->portfolio) {
            $portfolio = Portfolio::create(['internship_id' => $internship->id]);
            $internship->setRelation('portfolio', $portfolio->load('photos'));
        }

        return response()->json([
            'internship' => $internship,
            'user'       => $request->user()->load('studentProfile'),
        ]);
    }

    /**
     * POST /api/v1/student/portfolio
     * Update portfolio text fields.
     */
    public function savePortfolio(Request $request)
    {
        $internship = $this->internship($request);
        $portfolio  = Portfolio::firstOrCreate(['internship_id' => $internship->id]);

        $request->validate([
            // Chapter I
            'company_profile'                 => 'nullable|string|max:5000',
            'company_background'              => 'nullable|string|max:5000',
            'company_vision'                  => 'nullable|string|max:3000',
            'company_mission'                 => 'nullable|string|max:3000',
            'org_chart_caption'               => 'nullable|string|max:500',
            // Chapter III — raised to 3000 chars each for full essay responses
            'prof_ethical_responsibilities'   => 'nullable|string|max:3000',
            'things_learned'                  => 'nullable|string|max:3000',
            'experience_with_people'          => 'nullable|string|max:3000',
            'industry_best_practices'         => 'nullable|string|max:3000',
            'recommendations'                 => 'nullable|string|max:3000',
            'advice'                          => 'nullable|string|max:3000',
        ]);

        $data = $request->only([
            'company_profile',
            'company_background',
            'company_vision',
            'company_mission',
            'org_chart_caption',
            'prof_ethical_responsibilities',
            'things_learned',
            'experience_with_people',
            'industry_best_practices',
            'recommendations',
            'advice'
        ]);

        $portfolio->update($data);

        return response()->json(['message' => 'Portfolio data saved successfully', 'portfolio' => $portfolio]);
    }

    /**
     * POST /api/v1/student/portfolio/photos
     * Upload an OJT photo, org chart, company logo, or any appendix document.
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'type'        => 'required|string',
            'label'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'week_number' => 'nullable|integer|min:1',
            'date_taken'  => 'nullable|date',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $internship = $this->internship($request);
        $portfolio  = Portfolio::firstOrCreate(['internship_id' => $internship->id]);

        $path = $request->file('file')->store('portfolios/' . $internship->id, 'local');

        if ($request->type === 'org_chart') {
            if ($portfolio->org_chart_path) {
                Storage::disk('local')->delete($portfolio->org_chart_path);
            }
            $portfolio->update(['org_chart_path' => $path]);
            return response()->json(['message' => 'Org chart uploaded', 'path' => $path]);
        } elseif ($request->type === 'company_logo') {
            if ($portfolio->company_logo_path) {
                Storage::disk('local')->delete($portfolio->company_logo_path);
            }
            $portfolio->update(['company_logo_path' => $path]);
            return response()->json(['message' => 'Company logo uploaded', 'path' => $path]);
        } else {
            $photo = $portfolio->photos()->create([
                'file_path'   => $path,
                'type'        => $request->type,
                'label'       => $request->label,
                'description' => $request->description,
                'week_number' => $request->week_number,
                'date_taken'  => $request->date_taken,
                'sort_order'  => $request->sort_order ?? 0,
            ]);
            return response()->json(['message' => 'Document uploaded', 'photo' => $photo]);
        }
    }

    /**
     * DELETE /api/v1/student/portfolio/photos/{id}
     * Delete an uploaded document or photo.
     */
    public function deletePhoto(Request $request, $id)
    {
        $internship = $this->internship($request);
        $portfolio  = Portfolio::where('internship_id', $internship->id)->firstOrFail();

        $photo = $portfolio->photos()->where('id', $id)->firstOrFail();

        Storage::disk('local')->delete($photo->file_path);
        $photo->delete();

        return response()->json(['message' => 'Photo deleted']);
    }
}
