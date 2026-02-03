# Localization Implementation Examples

## Quick Start Examples

### Example 1: Language Switcher Component

**Blade View** (`resources/views/components/language-switcher.blade.php`):

```blade
<div class="language-switcher">
    <div class="dropdown">
        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button">
            {{ __('messages.language') ?? 'Language' }}
        </button>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('set-locale', 'en') }}">
                English
            </a>
            <a class="dropdown-item" href="{{ route('set-locale', 'ar') }}">
                العربية
            </a>
        </div>
    </div>
</div>
```

**Usage in Layout**:

```blade
<header>
    <nav class="navbar">
        @include('components.language-switcher')
    </nav>
</header>
```

---

### Example 2: Booking Management View

**Controller**:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Support\Facades\App;
use App\Helpers\LocalizationHelper;

class BookingsController extends Controller
{
    public function index()
    {
        $bookings = Booking::all();
        $isArabic = LocalizationHelper::isArabic();
        $direction = LocalizationHelper::getDirection();

        return view('bookings.index', compact('bookings', 'isArabic', 'direction'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_name' => 'required|string|max:255',
            'doctor_id' => 'required|exists:doctors,id',
            'clinic_id' => 'required|exists:clinics,id',
            'booking_date' => 'required|date|after:today',
        ]);

        $booking = Booking::create($validated);

        // Success message will use current locale
        return redirect()->route('bookings.show', $booking)
            ->with('success', __('messages.booking_accepted'));
    }
}
```

**View** (`resources/views/bookings/index.blade.php`):

```blade
<html dir="{{ $direction }}">
<head>
    <title>{{ __('messages.bookings') }}</title>
</head>
<body>
    <div class="container">
        <h1>{{ __('messages.bookings') }}</h1>

        @if($bookings->isEmpty())
            <p>{{ __('messages.no_data') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('messages.patient_name') }}</th>
                        <th>{{ __('messages.doctor_name') }}</th>
                        <th>{{ __('messages.booking_date') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th>{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $booking)
                        <tr>
                            <td>{{ $booking->patient_name }}</td>
                            <td>{{ $booking->doctor->name }}</td>
                            <td>{{ $booking->booking_date->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge">
                                    {{ __("messages.{$booking->status}") }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('bookings.show', $booking) }}">
                                    {{ __('messages.view') }}
                                </a>
                                <a href="{{ route('bookings.edit', $booking) }}">
                                    {{ __('messages.edit') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <a href="{{ route('bookings.create') }}" class="btn btn-primary">
            {{ __('messages.new_booking') }}
        </a>
    </div>
</body>
</html>
```

---

### Example 3: Form Validation with Messages

**Controller**:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;

class PatientsController extends Controller
{
    public function store(Request $request)
    {
        // Validation automatically uses current locale for error messages
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:patients',
            'phone' => 'required|string|regex:/^[0-9\+\-\s\(\)]+$/',
            'date_of_birth' => 'required|date|before:today',
        ]);

        $patient = Patient::create($validated);

        return redirect()->route('patients.show', $patient)
            ->with('success', __('messages.registration_successful') ?? 'Patient registered successfully');
    }
}
```

**Form View** (`resources/views/patients/form.blade.php`):

```blade
<form method="POST" action="{{ route('patients.store') }}">
    @csrf

    <div class="form-group">
        <label for="name">{{ __('messages.patient_name') }}</label>
        <input
            type="text"
            class="form-control @error('name') is-invalid @enderror"
            id="name"
            name="name"
            value="{{ old('name') }}"
            placeholder="{{ __('messages.patient_name') }}"
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="email">{{ __('messages.email') }}</label>
        <input
            type="email"
            class="form-control @error('email') is-invalid @enderror"
            id="email"
            name="email"
            value="{{ old('email') }}"
            placeholder="{{ __('messages.email') }}"
            required
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="phone">{{ __('messages.phone') }}</label>
        <input
            type="tel"
            class="form-control @error('phone') is-invalid @enderror"
            id="phone"
            name="phone"
            value="{{ old('phone') }}"
            placeholder="{{ __('messages.phone') }}"
            required
        >
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary">
        {{ __('messages.save') }}
    </button>
    <a href="{{ route('patients.index') }}" class="btn btn-secondary">
        {{ __('messages.back') }}
    </a>
</form>
```

---

### Example 4: Queue Management with Status Indicators

**Controller**:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Queue;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Helpers\LocalizationHelper;

class QueueController extends Controller
{
    public function index()
    {
        $queues = Queue::with('clinic', 'currentPatient', 'nextPatients')->get();

        return view('queue.index', [
            'queues' => $queues,
            'isArabic' => LocalizationHelper::isArabic(),
        ]);
    }

    public function callNext(Queue $queue)
    {
        $nextPatient = $queue->nextPatient();

        if (!$nextPatient) {
            return back()->with('info', __('messages.no_patients_waiting'));
        }

        $queue->update(['current_patient_id' => $nextPatient->id]);

        // Send notification
        // Notification::send($nextPatient->user, new PatientCalled());

        return back()->with('success', __('messages.queue_advanced'));
    }

    public function skipPatient(Queue $queue, Booking $booking)
    {
        $booking->update(['status' => 'skipped']);

        return back()->with('success', __('messages.patient_skipped'));
    }

    public function pauseQueue(Queue $queue)
    {
        $queue->update(['is_paused' => true]);

        return back()->with('info', __('messages.queue_paused'));
    }

    public function resumeQueue(Queue $queue)
    {
        $queue->update(['is_paused' => false]);

        return back()->with('info', __('messages.queue_resumed'));
    }
}
```

**View** (`resources/views/queue/index.blade.php`):

```blade
<div class="container">
    <h1>{{ __('messages.queue_manager') }}</h1>

    @foreach($queues as $queue)
        <div class="queue-card">
            <h3>{{ $queue->clinic->name }}</h3>

            <!-- Current Patient -->
            <div class="current-patient">
                <h4>{{ __('messages.now_serving') }}</h4>
                @if($queue->currentPatient)
                    <p class="token">
                        {{ __('messages.token_number') }}:
                        <strong>{{ $queue->currentPatient->token_number }}</strong>
                    </p>
                    <p>{{ $queue->currentPatient->patient->name }}</p>
                @else
                    <p class="text-muted">{{ __('messages.no_patients_waiting') }}</p>
                @endif
            </div>

            <!-- Queue Status -->
            <div class="queue-status">
                @if($queue->is_paused)
                    <span class="badge bg-warning">{{ __('messages.queue_paused') }}</span>
                @else
                    <span class="badge bg-success">{{ __('messages.active') }}</span>
                @endif
            </div>

            <!-- Actions -->
            <div class="queue-actions">
                @if($queue->is_paused)
                    <form action="{{ route('queue.resume', $queue) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            {{ __('messages.resume_queue') }}
                        </button>
                    </form>
                @else
                    <form action="{{ route('queue.pause', $queue) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            {{ __('messages.pause_queue') }}
                        </button>
                    </form>
                    <form action="{{ route('queue.call-next', $queue) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            {{ __('messages.call_next') }}
                        </button>
                    </form>
                @endif
            </div>

            <!-- Next Patients in Queue -->
            <div class="next-patients">
                <h5>{{ __('messages.next_up') }}</h5>
                @if($queue->nextPatients->count() > 0)
                    <ul class="list-group">
                        @foreach($queue->nextPatients->take(5) as $patient)
                            <li class="list-group-item">
                                <span class="token">{{ $patient->token_number }}</span>
                                {{ $patient->patient->name }}
                                <span class="badge">
                                    {{ __("messages.{$patient->status}") }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted">{{ __('messages.no_data') }}</p>
                @endif
            </div>
        </div>
    @endforeach
</div>
```

---

### Example 5: Dashboard with RTL Support

**View** (`resources/views/dashboard.blade.php`):

```blade
@php
    use App\Helpers\LocalizationHelper;
    $isArabic = LocalizationHelper::isArabic();
    $direction = LocalizationHelper::getDirection();
@endphp

<!DOCTYPE html>
<html dir="{{ $direction }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.dashboard') }} - {{ config('app.name') }}</title>

    @if($isArabic)
        <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @else
        <link rel="stylesheet" href="{{ asset('css/ltr.css') }}">
    @endif

    <style>
        body {
            direction: {{ $direction }};
            text-align: {{ $isArabic ? 'right' : 'left' }};
        }

        .margin-start {
            margin-{{ $isArabic ? 'right' : 'left' }}: 1rem;
        }

        .padding-end {
            padding-{{ $isArabic ? 'left' : 'right' }}: 1rem;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <h1>{{ __('messages.dashboard') }}</h1>
            @include('components.language-switcher')
        </nav>
    </header>

    <main class="container">
        <div class="dashboard-grid">
            <!-- Statistics Cards -->
            <div class="stats-section">
                <div class="stat-card">
                    <h3>{{ __('messages.bookings_today') }}</h3>
                    <p class="stat-value">{{ $bookingsToday }}</p>
                </div>

                <div class="stat-card">
                    <h3>{{ __('messages.patients_arrived') }}</h3>
                    <p class="stat-value">{{ $patientsArrived }}</p>
                </div>

                <div class="stat-card">
                    <h3>{{ __('messages.avg_wait_time') }}</h3>
                    <p class="stat-value">{{ $avgWaitTime }} {{ __('messages.minutes') }}</p>
                </div>

                <div class="stat-card">
                    <h3>{{ __('messages.active_clinics') }}</h3>
                    <p class="stat-value">{{ $activeClinics }}</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="actions-section">
                <a href="{{ route('bookings.create') }}" class="btn btn-primary">
                    {{ __('messages.new_booking') }}
                </a>
                <a href="{{ route('queue.index') }}" class="btn btn-secondary">
                    {{ __('messages.queue_manager') }}
                </a>
            </div>

            <!-- Recent Bookings -->
            <div class="bookings-section">
                <h2>{{ __('messages.pending_bookings') }}</h2>

                @forelse($recentBookings as $booking)
                    <div class="booking-item">
                        <div class="booking-info">
                            <h4>{{ $booking->patient->name }}</h4>
                            <p>{{ $booking->clinic->name }}</p>
                        </div>
                        <div class="booking-status">
                            <span class="badge badge-{{ $booking->status }}">
                                {{ __("messages.{$booking->status}") }}
                            </span>
                        </div>
                        <div class="booking-actions">
                            <a href="{{ route('bookings.show', $booking) }}">
                                {{ __('messages.view') }}
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">{{ __('messages.no_data') }}</p>
                @endforelse
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('messages.all_rights_reserved') }}</p>
    </footer>
</body>
</html>
```

---

### Example 6: API Response with Localization

**Controller**:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class BookingsApiController extends Controller
{
    public function index(): JsonResponse
    {
        $bookings = Booking::all();

        return response()->json([
            'success' => true,
            'message' => __('messages.success'),
            'data' => $bookings,
            'locale' => app()->getLocale(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_name' => 'required|string',
            'clinic_id' => 'required|exists:clinics,id',
        ]);

        try {
            $booking = Booking::create($validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.booking_accepted'),
                'data' => $booking,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.error'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Booking $booking): JsonResponse
    {
        try {
            $booking->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.booking_cancelled'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.error'),
            ], 500);
        }
    }
}
```

---

### Example 7: Email Notifications with Localization

**Notification**:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Booking;
use App\Helpers\LocalizationHelper;

class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    protected $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Set locale for this notification
        $previousLocale = LocalizationHelper::getCurrentLocale();
        LocalizationHelper::setLocale('ar'); // Send in Arabic

        $mailMessage = (new MailMessage)
            ->subject(__('messages.booking_accepted'))
            ->greeting(__('messages.hello') . ' ' . $notifiable->name)
            ->line(__('messages.booking_confirmed_text'))
            ->line(__('messages.clinic') . ': ' . $this->booking->clinic->name)
            ->line(__('messages.date') . ': ' . $this->booking->booking_date->format('Y-m-d'))
            ->line(__('messages.time') . ': ' . $this->booking->booking_time)
            ->action(__('messages.view_booking'), route('bookings.show', $this->booking))
            ->line(__('messages.thank_you'));

        // Reset locale
        LocalizationHelper::setLocale($previousLocale);

        return $mailMessage;
    }
}
```

**Usage**:

```php
$booking = Booking::find($id);
$booking->patient->user->notify(new BookingConfirmed($booking));
```

---

## Integration Checklist

- [ ] Verify translation files in `/lang/en/` and `/lang/ar/`
- [ ] Check middleware is registered in `app/Http/Kernel.php`
- [ ] Test language switcher routes work correctly
- [ ] Test form validation messages in both languages
- [ ] Verify Arabic text displays as RTL properly
- [ ] Test locale persistence across page reloads
- [ ] Update all views to use `__()` helper
- [ ] Test all controller messages use translations
- [ ] Verify emails send in correct language
- [ ] Test API responses include locale information
