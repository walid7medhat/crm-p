<?php

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with('user');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->from && $request->to) {
            $query->whereBetween('date', [$request->from, $request->to]);
        }

        return response()->json([
            'status' => true,
            'data' => $query->orderBy('date', 'desc')->get()
        ]);
    }

    public function today(Request $request)
    {
        $today = now('Asia/Dubai')->toDateString();

        $data = Attendance::with('user')
            ->where('date', $today)
            ->get();

        return response()->json([
            'status' => true,
            'date' => $today,
            'data' => $data
        ]);
    }
}