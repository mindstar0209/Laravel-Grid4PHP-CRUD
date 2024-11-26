<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
        @csrf

        <!-- Role -->
        <div>
            <x-input-label for="role" :value="__('Role')" />
            <select id="role" name="role"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 mt-1"
                required>
                <option value="user" selected>{{ __('User') }}</option>
                <option value="admin">{{ __('Admin') }}</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <!-- Name -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')"
                required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')"
                required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Address -->
        <div class="mt-4">
            <x-input-label for="address" :value="__('Address')" />
            <x-textarea-input id="address" class="block mt-1 w-full" type="text" name="address" :value="old('address')"
                required autofocus autocomplete="address" />
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>

        <!-- Phone number -->
        <div class="mt-4">
            <x-input-label for="phone_number" :value="__('Phone number')" />
            <x-text-input id="phone_number" class="block mt-1 w-full" type="text" name="phone_number"
                :value="old('phone_number')" required autofocus autocomplete="phone_number" />
            <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
        </div>

        <!-- Person in charge -->
        <div class="mt-4">
            <x-input-label for="person_in_charge" :value="__('Person in charge')" />
            <x-textarea-input id="person_in_charge" class="block mt-1 w-full" type="text" name="person_in_charge"
                :value="old('person_in_charge')" required autofocus autocomplete="person_in_charge" />
            <x-input-error :messages="$errors->get('person_in_charge')" class="mt-2" />
        </div>

        <!-- Logo Upload -->
        <div class="mt-4">
            <x-input-label for="logo" :value="__('Brand Logo')" />
            <input id="logo" class="block mt-1 w-full" type="file" name="logo"
                accept="image/jpeg,image/png,image/jpg" required onchange="previewLogo(event)" />
            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
            <img id="logo-preview" class="mt-2"
                style="width: 200px; height:200px; display: none; border-radius: 100%" />
        </div>

        <!-- License Type -->
        <div class="mt-4">
            <x-input-label for="license_type" :value="__('License Type')" />
            <select id="license_type" name="license_type"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 mt-1"
                required>
                <option value="Single User" selected>{{ __('Single User') }}</option>
                <option value="Unlimited User">{{ __('Unlimited User') }}</option>
            </select>
            <x-input-error :messages="$errors->get('license_type')" class="mt-2" />
        </div>

        <!-- Sub Domain -->
        <div class="mt-4">
            <x-input-label for="sub_domain" :value="__('Sub Domain')" />
            <x-text-input id="sub_domain" class="block mt-1 w-full" type="text" name="sub_domain" :value="old('sub_domain')"
                required autofocus autocomplete="sub_domain" />
            <x-input-error :messages="$errors->get('sub_domain')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required
                autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password"
                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

<script>
    function previewLogo(event) {
        const logoPreview = document.getElementById('logo-preview');
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreview.src = e.target.result;
                logoPreview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    }
</script>
