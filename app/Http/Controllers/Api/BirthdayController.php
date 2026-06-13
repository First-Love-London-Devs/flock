<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BirthdayNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BirthdayController extends Controller
{
    public function upcoming(Request $request, BirthdayNotificationService $service): JsonResponse
    {
        $leaderId = $request->user()->id;
        $days = (int) $request->query('days', 14);

        $birthdays = $service->getUpcomingBirthdays($leaderId, $days);

        return response()->json(['success' => true, 'data' => $birthdays]);
    }
}
