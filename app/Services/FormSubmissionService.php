<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class FormSubmissionService
{
    public function handle(Request $request, Form $form): array
    {
        $rules = $this->buildValidationRules($form->fields ?? []);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return [
                'success' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'page_id' => $request->input('page_id'),
            'data' => $validator->validated(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($form->recipient_email) {
            $this->sendNotification($form, $submission);
        }

        return ['success' => true, 'submission' => $submission];
    }

    private function buildValidationRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            if (! $name) {
                continue;
            }

            $fieldRules = [];

            if (! empty($field['required'])) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['type'] ?? 'text') {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'file':
                    $fieldRules[] = 'file';
                    if (! empty($field['max_size'])) {
                        $fieldRules[] = 'max:' . $field['max_size'];
                    }
                    break;
                case 'select':
                    if (! empty($field['options'])) {
                        $values = array_column($field['options'], 'value');
                        $fieldRules[] = 'in:' . implode(',', $values);
                    }
                    break;
            }

            if (! empty($field['min_length'])) {
                $fieldRules[] = 'min:' . $field['min_length'];
            }

            if (! empty($field['max_length'])) {
                $fieldRules[] = 'max:' . $field['max_length'];
            }

            $rules[$name] = $fieldRules;
        }

        return $rules;
    }

    private function sendNotification(Form $form, FormSubmission $submission): void
    {
        try {
            Mail::raw(
                $this->buildEmailBody($form, $submission),
                function ($message) use ($form) {
                    $message->to($form->recipient_email)
                        ->subject('New form submission: ' . $form->name);
                }
            );
        } catch (\Exception $e) {
            // Log notification failure without breaking submission flow
            logger()->error('Form submission notification failed', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildEmailBody(Form $form, FormSubmission $submission): string
    {
        $lines = ["New submission for form: {$form->name}", ''];
        foreach ($submission->data as $key => $value) {
            $lines[] = "{$key}: " . (is_array($value) ? implode(', ', $value) : $value);
        }
        $lines[] = '';
        $lines[] = 'Submitted at: ' . $submission->created_at->toDateTimeString();
        return implode("\n", $lines);
    }
}
