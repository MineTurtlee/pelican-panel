<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\{TextInput, Checkbox};
use Filament\Forms\Form;
use Filament\Pages\SimplePage;
use App\Extensions\Captcha\CaptchaService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Illuminate\Validation\ValidationException;

class Register extends SimplePage
{
    protected CaptchaService $captchaService;

    public function boot(CaptchaService $captchaService): void
    {
        $this->captchaService = $captchaService;
    }

    public function form(Schema $schema): Schema
    {
        $components = [
            TextInput::make('data.username')
                ->label('Username')
                ->required()
                ->autofocus()
                ->maxLength(255),
            TextInput::make('data.email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255),
            TextInput::make('data.password')
                ->label('Password')
                ->password()
                ->required()
                ->minLength(8),
            TextInput::make('data.password_confirmation')
                ->label('Confirm Password')
                ->password()
                ->required()
                ->same('data.password'),
        ];

        if ($captchaComponent = $this->getCaptchaComponent()) {
            $components[] = $captchaComponent;
        }

        return $schema
            ->components($components);
    }

    private function getCaptchaComponent(): ?Component
    {
        return $this->captchaService->getActiveSchema()?->getFormComponent();
    }

    protected function throwFailureValidationException(): never
    {
        $this->dispatch('reset-captcha');

        throw ValidationException::withMessages([
            'data.register' => trans('filament-panels::auth/pages/register.messages.failed')]);
    }

    protected function getRegisterFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(trans('filament-panels::auth/pages/register.title'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $loginType = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $loginType => mb_strtolower($data['login']),
            'password' => $data['password'],
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('register')
                ->label('Register')
                ->color(Color::Blue)
                ->action('registerUser'),
        ];
    }

    public function registerUser()
    {
        $data = $this->form->getState();

        
        if (! $this->captchaService->validate($data)) {
            $this->throwFailureValidationException();
        }

        if ($data['data']['password'] !== $data['data']['password_confirmation']) {
            throw ValidationException::withMessages([
                'data.password' => 'Passwords do not match.',
            ]);
        }

        $user = \App\Models\User::create([
            'username' => $data['data']['username'],
            'email' => $data['data']['email'],
            'password' => bcrypt($data['data']['password']),
        ]);

        auth()->login($user);

        return redirect()->route('filament.pages.dashboard');
    }
}