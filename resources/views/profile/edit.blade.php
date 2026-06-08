@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .profile-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .profile-card {
        border-radius: 16px;
        background: white;
        border: 1px solid #e2e8f0;
        padding: 2rem;
        margin-bottom: 2rem;
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
