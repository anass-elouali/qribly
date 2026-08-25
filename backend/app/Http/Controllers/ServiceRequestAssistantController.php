<?php

namespace App\Http\Controllers;

use App\Http\Requests\InterpretServiceRequestRequest;
use App\Services\ServiceRequestInterpretationService;
use Illuminate\Http\JsonResponse;

class ServiceRequestAssistantController extends Controller
{
    public function __invoke(
        InterpretServiceRequestRequest $request,
        ServiceRequestInterpretationService $interpreter,
    ): JsonResponse {
        $result = $interpreter->interpret(
            $request->user(),
            $request->validated('raw_text'),
        );

        if ($result === null) {
            return response()->json([
                'message' => "L'assistant ne peut pas analyser ta demande pour le moment.",
                'fallback' => true,
            ], 503);
        }

        return response()->json($result);
    }
}
