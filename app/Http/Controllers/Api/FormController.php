<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\FormSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct(private FormSubmissionService $submissionService)
    {
    }

    public function show(Form $form): JsonResponse
    {
        if (! $form->submissions_enabled) {
            return response()->json([
                'message' => 'This form is not accepting submissions.',
            ], 403);
        }

        return response()->json([
            'data' => $form->only(['id', 'name', 'description', 'fields', 'confirmation_message']),
        ]);
    }

    public function submit(Request $request, Form $form): JsonResponse
    {
        if (! $form->submissions_enabled) {
            return response()->json([
                'message' => 'This form is not accepting submissions.',
            ], 403);
        }

        $result = $this->submissionService->handle($request, $form);

        if (! $result['success']) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'message' => $form->confirmation_message ?? 'Your submission has been received.',
            'redirect_url' => $form->redirect_url,
        ], 201);
    }
}
