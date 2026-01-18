<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
</head>

<body>
    <h2>Hello {{ $reservation->customer_name }}</h2>

    <p>Your reservation has been <strong>confirmed ✅</strong></p>

    <p>
        Reservation Date: <strong>{{ $reservation->date }}</strong>
    </p>

    <p>Thank you for choosing us ☕</p>
</body>

</html>
