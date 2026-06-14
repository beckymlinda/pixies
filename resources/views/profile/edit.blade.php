@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    /* ===== PROFILE PAGE ===== */

.profile-header {
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    margin: -1.5rem -1.5rem 2rem;
    padding: 1.75rem 2rem;
}

.profile-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    transition: all .2s ease;
}

.profile-card:hover {
    box-shadow: 0 10px 25px rgba(0,0,0,.06);
}

/* ===== FIX JETSTREAM/BREEZE FORM ALIGNMENT ===== */

.profile-card .max-w-xl {
    max-width: 100% !important;
}

.profile-card form {
    width: 100%;
}

.profile-card .mt-6,
.profile-card .mt-4,
.profile-card .mt-3 {
    margin-top: 1rem !important;
}

.profile-card .space-y-6 > * + *,
.profile-card .space-y-4 > * + * {
    margin-top: 1rem !important;
}

/* ===== LABELS ===== */

.profile-card label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: .5rem;
}

/* ===== INPUTS ===== */

.profile-card input,
.profile-card select,
.profile-card textarea {
    width: 100%;
    min-height: 46px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    padding: .75rem 1rem;
    font-size: .95rem;
    transition: all .2s ease;
    background: #fff;
}

.profile-card input:focus,
.profile-card select:focus,
.profile-card textarea:focus {
    border-color: #111827;
    box-shadow: 0 0 0 4px rgba(17, 24, 39, .08);
    outline: none;
}

/* ===== BUTTONS ===== */

.profile-card button,
.profile-card .btn {
    min-height: 44px;
    padding: .7rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
}

/* ===== ACTION ROWS ===== */

.profile-card .flex.items-center.gap-4,
.profile-card .flex.items-center {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

/* ===== VALIDATION ERRORS ===== */

.profile-card .text-red-600,
.profile-card .text-red-500 {
    font-size: .85rem;
    margin-top: .35rem;
    display: block;
}

/* ===== SIDEBAR PROFILE CARD ===== */

.avatar-lg {
    width: 90px !important;
    height: 90px !important;
    font-size: 2rem;
    margin-bottom: 1rem;
}

.profile-card hr {
    border-color: #e5e7eb;
}

/* ===== RESPONSIVE ===== */

@media (max-width: 992px) {
    .profile-header {
        padding: 1.25rem;
    }

    .profile-card {
        padding: 1.25rem;
    }

    .col-lg-4 {
        margin-top: 1rem;
    }
}
    
    /* ===== BUTTON FIXES ===== */

.profile-card button[type="submit"],
.profile-card .btn-primary,
.profile-card button {
    background: #111827 !important;
    color: #ffffff !important;
    border: 1px solid #111827 !important;
    font-weight: 600;
}

.profile-card button[type="submit"]:hover,
.profile-card .btn-primary:hover,
.profile-card button:hover {
    background: #000000 !important;
    color: #ffffff !important;
    border-color: #000000 !important;
}

/* Delete account button */
.profile-card .bg-red-600,
.profile-card .bg-red-500 {
    background: #dc2626 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
}

.profile-card .bg-red-600:hover,
.profile-card .bg-red-500:hover {
    background: #b91c1c !important;
    color: #ffffff !important;
}

/* Success messages */
.profile-card .text-green-600,
.profile-card .text-green-500 {
    color: #16a34a !important;
}

/* Ensure all button text stays visible */
.profile-card button *,
.profile-card .btn * {
    color: inherit !important;
}
</style>

<div class="container-fluid p-0">
    <div class="profile-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h4 fw-bold mb-0 text-dark">Profile Settings</h1>
            <p class="text-muted small mb-0">Update your account information and security preferences.</p>
        </div>
    </div>

    <div class="px-4 pb-5">
        <div class="row">
            <div class="col-lg-8">
                <!-- Update Profile Information -->
                <div class="profile-card shadow-sm">
                    <h5 class="fw-bold text-dark mb-4">Personal Information</h5>
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <!-- Update Password -->
                <div class="profile-card shadow-sm">
                    <h5 class="fw-bold text-dark mb-4">Security Settings</h5>
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <!-- Delete Account -->
                <div class="profile-card shadow-sm border-danger border-opacity-25">
                    <h5 class="fw-bold text-danger mb-4">Danger Zone</h5>
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="profile-card shadow-sm bg-light bg-opacity-50 text-center">
                    <div class="avatar-lg mx-auto mb-3 d-flex align-items-center justify-content-center bg-dark text-white rounded-circle fw-bold fs-2" style="width: 80px; height: 80px;">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <h5 class="fw-bold text-dark mb-1">{{ auth()->user()->name }}</h5>
                    <p class="text-muted small mb-3">{{ auth()->user()->email }}</p>
                    <span class="badge bg-primary rounded-pill px-3 py-2">{{ strtoupper(auth()->user()->role) }}</span>
                    
                    <hr class="my-4">
                    
                    <div class="text-start">
                        <div class="small text-muted mb-1">MEMBER SINCE</div>
                        <div class="fw-bold text-dark">{{ auth()->user()->created_at->format('M Y') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
