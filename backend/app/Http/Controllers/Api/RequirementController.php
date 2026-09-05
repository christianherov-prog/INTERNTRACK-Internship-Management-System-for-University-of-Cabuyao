<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OjtRequirementTemplate;
use Illuminate\Http\Request;

class RequirementController extends Controller
{
    /** GET /v1/coordinator/requirements — list all requirements */
    public function index()
    {
        $requirements = OjtRequirementTemplate::orderBy('sort_order')->get();
        return response()->json($requirements);
    }

    /** POST /v1/coordinator/requirements */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|in:pre-ojt,during,post-ojt,general',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);

        $req = OjtRequirementTemplate::create($data);
        \App\Support\RequiredDocuments::clearCache();
        return response()->json($req, 201);
    }

    /** PUT /v1/coordinator/requirements/{id} */
    public function update(Request $request, $id)
    {
        $req = OjtRequirementTemplate::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'sometimes|required|in:pre-ojt,during,post-ojt,general',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);

        $req->update($data);
        \App\Support\RequiredDocuments::clearCache();
        return response()->json($req);
    }

    /** DELETE /v1/coordinator/requirements/{id} — soft-disable only */
    public function destroy($id)
    {
        $req = OjtRequirementTemplate::findOrFail($id);
        $req->update(['is_active' => false]);
        \App\Support\RequiredDocuments::clearCache();
        return response()->json(['message' => 'Requirement disabled successfully.']);
    }
}
