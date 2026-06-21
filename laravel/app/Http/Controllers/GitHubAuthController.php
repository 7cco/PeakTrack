<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GitHubAuthController extends Controller
{
    // 1. Редирект на GitHub
    public function redirectToGithub()
    {
        return Socialite::driver('github')->redirect();
    }

    // 2. Обработка ответа от GitHub
    public function handleGithubCallback()
    {
        try {
            $githubUser = Socialite::driver('github')->user();
            \Log::info('GitHub user data:', [
                'id' => $githubUser->getId(),
                'email' => $githubUser->getEmail(),
                'name' => $githubUser->getName(),
            ]);
        } catch (\Exception $e) {
            \Log::error('GitHub Auth Error: ' . $e->getMessage());
            return redirect('/login')->with('error', 'Ошибка: ' . $e->getMessage());
        }

        $user = User::where('github_id', $githubUser->getId())->first();

        if (!$user) {
            $email = $githubUser->getEmail();
            
            // Если GitHub скрыл email, генерируем заглушку
            if (!$email) {
                $email = $githubUser->getId() . '@users.noreply.github.com';
                \Log::warning('GitHub email is private, using: ' . $email);
            }

            $user = User::where('email', $email)->first();
            
            if ($user) {
                $user->update([
                    'github_id' => $githubUser->getId(),
                    'github_token' => $githubUser->token,
                ]);
            } else {
                $user = User::create([
                    'name' => $githubUser->getName() ?? $githubUser->getNickname() ?? 'GitHub User',
                    'email' => $email,
                    'github_id' => $githubUser->getId(),
                    'github_token' => $githubUser->token,
                    'password' => bcrypt(\Str::random(24)),
                ]);
            }
        }

        Auth::login($user, true);
        
        \Log::info('User logged in: ' . $user->id);
        
        return redirect('/habits');
    }
}