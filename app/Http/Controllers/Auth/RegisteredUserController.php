<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'in:user,admin'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'address' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'person_in_charge' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'logo' => ['nullable', 'max:2048'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:2048'],
            'license_type' => ['required', 'string', 'in:Single User,Unlimited User'],
            'sub_domain' => ['required', 'string', 'max:50']
        ]);

         // Check if the logo file is uploaded
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            // Store the logo and get its path
            $logoPath = $request->file('logo')->store('logos', 'public');
        } else {
            // Handle the error, e.g., return with an error message
            return redirect()->back()->withErrors(['logo' => 'Logo upload failed. Please try again.']);
        }
        $attachmentPath = $request->file('attachment') ? $request->file('attachment')->store('attachments', 'public') : null;

        $user = User::create([
            'role' => $request->role,
            'name' => $request->name,
            'email' => $request->email,
            'address' => $request->address,
            'phone_number' => $request->phone_number,
            'person_in_charge' => $request->person_in_charge,
            'password' => Hash::make($request->password),
            'logo' => $logoPath,
            'attachment' => $attachmentPath,
            'license_type' => $request->license_type,
            'sub_domain' => $request->sub_domain
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
