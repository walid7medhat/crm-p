<?php
namespace App\Helpers;

class ApiResponse
{
   
  public static function success($data = null, $message = 'Success', int $code = 200, $meta = null)
    {
        $response = [
            'status' => true,
            'message' => $message,
            'data' => $data,
        ];

        // إضافة metadata إذا موجودة
        if ($meta) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }
    
    public static function error(string $message='Something went wrong', int $code=400, $errors=null) {
        $response = ['status'=>false,'message'=>$message];
        if ($errors) $response['errors'] = $errors;
        return response()->json($response,$code);
    }
}
