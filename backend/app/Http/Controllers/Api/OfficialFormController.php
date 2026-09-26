<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Services\OfficialFormDataService;
use App\Support\InternshipAccess;
use Illuminate\Http\Request;

class OfficialFormController extends Controller
{
    public function show(Request $request, Internship $internship)
    {
        InternshipAccess::abortUnlessCanView($request->user(), $internship);

        return response()->json(app(OfficialFormDataService::class)->bundle($internship, $request->user()));
    }
}
