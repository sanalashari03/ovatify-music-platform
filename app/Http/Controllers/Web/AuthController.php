<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Events\VerificationCodeGenerated;
use Exception;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|min:7|max:20|unique:users,phone',
            'password' => 'required|string|min:8',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => $request->password, // Password hashing is handled by User model attribute casting/mutator
                'role' => 'consumer'
            ]);

            // Generate Verification Code
            $code = '0000';
            VerificationCode::create([
                'user_id' => $user->id,
                'code' => $code,
                'type' => 'email',
                'expires_at' => Carbon::now()->addMinutes(5)
            ]);

            event(new VerificationCodeGenerated($user, $code));

            DB::commit();

            // Store user in session or just redirect to verification
            session(['temp_user_id' => $user->id]);

            return redirect()->route('verification')->with('success', 'Registration successful! Please verify your email.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Web registration error: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended(route('consumer.dashboard.index'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|array|min:4',
            'code.*' => 'required|numeric|digits:1',
        ]);

        $userId = session('temp_user_id');

        if (!$userId) {
            return redirect()->route('register')->withErrors(['error' => 'Session expired. Please register again.']);
        }

        $inputCode = implode('', $request->code);

        $verification = VerificationCode::where('user_id', $userId)
            ->where('code', $inputCode)
            ->where('type', 'email')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$verification) {
            return back()->withErrors(['error' => 'Invalid or expired verification code.']);
        }

        // Verify successful
        $user = User::find($userId);

        if ($user) {
            // Mark email as verified if needed (optional depending on your User model)
            if (method_exists($user, 'markEmailAsVerified')) {
                $user->markEmailAsVerified();
            } else {
                $user->email_verified_at = Carbon::now();
                $user->save();
            }

            Auth::login($user);
            session()->forget('temp_user_id');
            // Delete code used
            $verification->delete();

            return redirect()->route('consumer.dashboard.index');
        }

        return back()->withErrors(['error' => 'User not found.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function initForgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        if ($user) {
            session(['forgot_password_user_id' => $user->id]);
            return redirect()->route('forgot.password');
        }
        return back()->withErrors(['email' => 'Could not find an account with that email.']);
    }

    public function showForgotPasswordOptions()
    {
        $userId = session('forgot_password_user_id');
        if (!$userId) return redirect()->route('login');

        $user = User::find($userId);
        
        // Mask Phone
        $phone = $user->phone ?? 'Unknown';
        $maskedPhone = (strlen($phone) > 6) ? substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2) : '***';
        
        // Mask Email
        $email = $user->email;
        $emailParts = explode('@', $email);
        $userStr = $emailParts[0];
        $maskedEmail = (strlen($userStr) > 2 ? substr($userStr, 0, -2) : $userStr[0]) . '***@' . $emailParts[1];

        return view('auth.forgot-password', compact('maskedPhone', 'maskedEmail'));
    }

    public function sendResetCode(Request $request)
    {
        $userId = session('forgot_password_user_id');
        if (!$userId) return redirect()->route('login');

        $user = User::find($userId);
        $method = $request->input('method', 'email');
        
        $code = '0000';
        VerificationCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'type' => 'reset',
            'expires_at' => Carbon::now()->addMinutes(5)
        ]);

        session(['reset_method' => $method]);
        return redirect()->route('forgot.verification')->with('success', 'Reset code sent to your ' . $method);
    }

    public function showForgotVerification()
    {
        $userId = session('forgot_password_user_id');
        if (!$userId) return redirect()->route('login');

        $user = User::find($userId);
        $method = session('reset_method', 'email');
        
        $contact = '';
        if ($method == 'sms') {
            $phone = $user->phone;
            $contact = (strlen($phone) > 6) ? substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2) : '***';
        } else {
            $email = $user->email;
            $emailParts = explode('@', $email);
            $userStr = $emailParts[0];
            $contact = (strlen($userStr) > 2 ? substr($userStr, 0, -2) : $userStr[0]) . '***@' . $emailParts[1];
        }

        return view('auth.forgot-verification', compact('contact'));
    }

    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'code' => 'required|array|min:4',
            'code.*' => 'required|numeric|digits:1',
        ]);

        $userId = session('forgot_password_user_id');
        if (!$userId) return redirect()->route('login')->withErrors(['error' => 'Session expired.']);

        $inputCode = implode('', $request->code);

        $verification = VerificationCode::where('user_id', $userId)
            ->where('code', $inputCode)
            ->where('type', 'reset')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$verification) {
            return back()->withErrors(['error' => 'Invalid or expired verification code.']);
        }

        $verification->delete();
        session(['can_reset_password' => true]);

        return redirect()->route('reset.password');
    }

    public function showResetPassword()
    {
        if (!session('can_reset_password') || !session('forgot_password_user_id')) {
            return redirect()->route('login');
        }
        return view('auth.create-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8'
        ]);

        $userId = session('forgot_password_user_id');
        if (!session('can_reset_password') || !$userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized or session expired.'], 401);
        }

        $user = User::find($userId);
        if ($user) {
            $user->password = $request->password;
            $user->save();
            
            Auth::login($user);

            session()->forget('forgot_password_user_id');
            session()->forget('can_reset_password');
            session()->forget('reset_method');

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'User not found.'], 404);
    }
}
