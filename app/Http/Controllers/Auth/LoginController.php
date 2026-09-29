<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\AccountInactive;
use App\Exceptions\Auth\InvalidCredentials;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AdminAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AdminAuthenticator $authenticator)
    {
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        try {
            $this->authenticator->attempt(
                $request->identifier(),
                $request->string('password')->toString(),
                $request->shouldRemember(),
            );
        } catch (InvalidCredentials) {
            $request->hitRateLimiter();

            // One message for "no such account" and "wrong password".
            throw ValidationException::withMessages([
                'identifier' => 'These credentials do not match our records.',
            ]);
        } catch (AccountInactive) {
            throw ValidationException::withMessages([
                'identifier' => 'Your account has been deactivated. Please contact the Super Admin.',
            ]);
        }

        $request->clearRateLimiter();

        // New session id on privilege change: defeats session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('admin.home'))
            ->with('toast', ['type' => 'success', 'message' => 'Logged in successfully.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authenticator->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('toast', ['type' => 'success', 'message' => 'You have been logged out.']);
    }
}
