<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     *  Success response
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     *  Error response
     */
    protected function errorResponse(string $message = 'Something went wrong', int $code = 400, $errors = null)
    {
        $response = [
            'status' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     *  Unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized')
    {
        return $this->errorResponse($message, 401);
    }

    /**
     *  Not Found response
     */
    protected function notFoundResponse(string $message = 'Resource not found')
    {
        return $this->errorResponse($message, 404);
    }

    /**
     *  Validation Error
     */
    protected function validationErrorResponse($errors, string $message = 'Validation error')
    {
        return $this->errorResponse($message, 422, $errors);
    }
}
