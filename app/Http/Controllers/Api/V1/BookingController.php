<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request) : JsonResponse
    {
        $bookingData = Booking::query()
            ->latest()
            ->cursorPaginate(15);
        return BookingResource::collection($bookingData)->response();
    }


    public function store(BookingRequest $request, ) : JsonResponse
    {

    }
}
