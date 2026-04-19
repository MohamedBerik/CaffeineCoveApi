<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReservationController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Reservation::class, 'reservation');
    }
    /**
     * Get all reservations for current company
     */
    public function index(Request $request)
    {
        $query = Reservation::query()->latest();

        return response()->json([
            'status' => 200,
            'data' => $query->get(),
        ]);
    }

    /**
     * Store new reservation
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'phone'    => 'nullable|string|max:50',
            'persons'  => 'required|integer|min:1',
            'date'     => 'required|date',
            'time'     => 'required',
            'message'  => 'nullable|string|max:1000',
        ]);

        $reservation = Reservation::create([
            'company_id' => Tenant::id(),
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone'] ?? null,
            'persons'    => $validated['persons'],
            'date'       => $validated['date'],
            'time'       => $validated['time'],
            'message'    => $validated['message'] ?? null,
            'status'     => 'pending',
        ]);

        return response()->json([
            'status'      => 201,
            'message'     => 'Reservation created successfully',
            'data'        => $reservation,
        ], 201);
    }

    /**
     * Confirm reservation
     */
    public function confirm($id)
    {
        $reservation = Reservation::query()->find($id);

        if (!$reservation) {
            return response()->json([
                'status' => 404,
                'message' => 'Reservation not found'
            ], 404);
        }

        if ($reservation->status === 'confirmed') {
            return response()->json([
                'status' => 409,
                'message' => 'Reservation already confirmed'
            ], 409);
        }

        $reservation->update(['status' => 'confirmed']);

        // Send confirmation email
        try {
            Mail::to($reservation->email)
                ->send(new ReservationConfirmed($reservation));
        } catch (\Exception $e) {
            Log::error('Failed to send reservation confirmation email', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Reservation confirmed successfully',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Cancel reservation
     */
    public function cancel($id)
    {
        $reservation = Reservation::query()->find($id);

        if (!$reservation) {
            return response()->json([
                'status' => 404,
                'message' => 'Reservation not found'
            ], 404);
        }

        if ($reservation->status === 'cancelled') {
            return response()->json([
                'status' => 409,
                'message' => 'Reservation already cancelled'
            ], 409);
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'status' => 200,
            'message' => 'Reservation cancelled successfully',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Delete reservation
     */
    public function destroy($id)
    {
        $reservation = Reservation::query()->find($id);

        if (!$reservation) {
            return response()->json([
                'status' => 404,
                'message' => 'Reservation not found'
            ], 404);
        }

        $reservation->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Reservation deleted successfully',
        ]);
    }
}
