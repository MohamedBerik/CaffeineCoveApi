<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReservationController extends Controller
{
    // --------------------
    // Get all reservations
    // --------------------
    public function index()
    {
        return response()->json([
            'status' => true,
            'data'   => Reservation::latest()->get(),
        ]);
    }

    // --------------------
    // Store new reservation
    // --------------------
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'persons'  => 'required|integer|min:1',
            'date'     => 'required|date',
            'time'     => 'required',
            'message'  => 'nullable|string|max:1000',
        ]);

        $reservation = Reservation::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'persons'  => $validated['persons'],
            'date'     => $validated['date'],
            'time'     => $validated['time'],
            'message'  => $validated['message'] ?? null,
            'status'   => 'pending',
        ]);

        return response()->json([
            'status'      => true,
            'message'     => 'Reservation created successfully',
            'reservation' => $reservation,
        ], 201);
    }
    // Confirm reservation
    public function confirm($id)
    {
        $reservation = Reservation::find($id);
        if (!$reservation) return response()->json(['message' => 'Not found'], 404);

        $reservation->status = 'confirmed'; // أو 1
        $reservation->save();

        // إرسال الإيميل
        Mail::to($reservation->email)
            ->send(new ReservationConfirmed($reservation));

        return response()->json([
            'success' => true,
            'message' => 'Reservation confirmed and email sent'
        ]);
    }

    // Cancel reservation
    public function cancel($id)
    {
        $reservation = Reservation::find($id);
        if (!$reservation) return response()->json(['message' => 'Not found'], 404);

        $reservation->status = 'cancelled'; // أو 0
        $reservation->save();

        return response()->json([
            'success' => true,
            'message' => 'Reservation cancelled'
        ]);
    }
}
