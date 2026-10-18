<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Validacion del inicio de sesion.
 *
 * Se separo del Controlador para tener las reglas y los mensajes en espanol en
 * un solo lugar, y para que el Controlador solo orchestration.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => 'usuario',
            'password' => 'contrasena',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'El usuario es obligatorio.',
            'username.max' => 'El usuario no puede tener mas de 50 caracteres.',
            'password.required' => 'La contrasena es obligatoria.',
            'password.max' => 'La contrasena es demasiado larga.',
        ];
    }

    /**
     * Credenciales verificadas.
     *
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return [
            'username' => $this->string('username')->trim()->toString(),
            'password' => $this->string('password')->toString(),
        ];
    }

    /**
     * Falla el login con 422 si las credenciales no coinciden.
     */
    public function authenticate(): void
    {
        $credentials = $this->credentials();

        if (! auth()->attempt($credentials, (bool) $this->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => 'Las credenciales no son correctas.',
            ]);
        }
    }
}
