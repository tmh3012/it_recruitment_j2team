<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{

    public function login()
    {
        return view('auth.login');
    }

    public function handlerLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required','min:8'],
        ]);
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $getRole = (auth()->user()->role);
            $role = strtolower(UserRoleEnum::getKey($getRole));
            return redirect()->route("${role}.index");
        } else {
            return redirect()->back()
                ->withInput()
                ->withErrors(['msg' => 'The Message']);
        }
    }

    public function callback($provider): RedirectResponse
    {
        $data = Socialite::driver($provider)->user();
        $user = User::query()
            ->where('email', $data->getEmail())
            ->first();
        $checkExist = true;

        if (is_null($user)) {
            $user = new User();
            $user->email = $data->getEmail();
            $user->role = UserRoleEnum::APPLICANT;
            $checkExist = false;
        }

        $user->name = $data->getName();
        $user->avatar = $data->getAvatar();
        auth()->login($user, true);
        if ($checkExist) {
            $role = strtolower(UserRoleEnum::getKey($user->role));
            return redirect()->route($role.'.index');
        }
        return redirect()->route('register');
    }

    public function register()
    {
        $roles = UserRoleEnum::getRolesForRegister();
        return view('auth.register', [
            'roles' => $roles,
        ]);
    }

    public function registering(Request $request)
    {
        if (auth()->check()) {
            $infoUpdate = $request->validate([
                'role' => [
                    'required',
                    Rule::in(UserRoleEnum::getRolesForRegister())
                ],
                'password' => [
                    'required',
                    'min:8',
                    'required_with:password_confirmation',
                ],
                'password_confirmation' => [
                    'required',
                    'min:8',
                ],
            ]);
            $infoUpdate['password'] = Hash::make($infoUpdate['password']);
            $role = strtolower(UserRoleEnum::getKey($infoUpdate['role']));
            User::query()
                ->where('id', auth()->user()->id)
                ->update([
                    'password' => $infoUpdate['password'] ,
                    'role' => $infoUpdate['role'],
                ]);
            redirect()->route("${role}.index");
        } else {
            $data = $request->validate([
                'name' => [
                    'required',
                    'string',
                ],
                'email' => [
                    'required',
                    'email:rfc,dns',
                    Rule::unique(User::class)
                ],
                'role' => [
                    'required',
                    Rule::in(UserRoleEnum::getRolesForRegister())
                ],
                'password' => [
                    'required',
                    'min:8',
                    'required_with:password_confirmation',
                ],
                'password_confirmation' => [
                    'required',
                    'min:8',
                ],
            ]);
            $data['password'] = Hash::make($data['password']);
            $role = strtolower(UserRoleEnum::getKey($data['role']));
            $user = User::create($data);
            Auth::login($user);
            return redirect()->route("${role}.index");
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return back();
    }


}
