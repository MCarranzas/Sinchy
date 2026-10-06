<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password_actual' => 'contrasena actual',
            'password' => 'nueva contrasena',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password_actual.required' => 'Debe indicar la contrasena actual.',
            'password.required' => 'La nueva contrasena es obligatoria.',
            'password.confirmed' => 'La confirmacion no coincide con la nueva contrasena.',
        ];
    }

    /**
     * Cambia la contrasena y limpia el flag de cambio obligatorio.
     */
    public function cambiar(): void
    {
        $this->user()->update([
            'password' => $this->string('password')->toString(),
            'debe_cambiar_password' => false,
        ]);
    }
}
