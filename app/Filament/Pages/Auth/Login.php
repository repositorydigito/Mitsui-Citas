<?php

namespace App\Filament\Pages\Auth;

use App\Services\Auth\DocumentAuthService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.corporate-login';

    public bool $showPasswordField = true;
    public bool $userExists = false;

    /**
     * Detectar información del dispositivo desde el User-Agent
     */
    protected function getDeviceInfo(): array
    {
        $userAgent = request()->userAgent() ?? 'Unknown';
        $ip = request()->ip();

        $isMobile = false;
        $deviceType = 'Desktop';
        $os = 'Unknown';
        $browser = 'Unknown';

        // Detectar si es móvil
        if (preg_match('/(android|iphone|ipad|ipod|blackberry|windows phone)/i', $userAgent)) {
            $isMobile = true;

            // Detectar OS móvil
            if (preg_match('/android/i', $userAgent)) {
                $os = 'Android';
                $deviceType = 'Android';
            } elseif (preg_match('/(iphone|ipad|ipod)/i', $userAgent)) {
                $os = 'iOS';
                $deviceType = preg_match('/ipad/i', $userAgent) ? 'iPad' : 'iPhone';
            } elseif (preg_match('/blackberry/i', $userAgent)) {
                $os = 'BlackBerry';
                $deviceType = 'BlackBerry';
            } elseif (preg_match('/windows phone/i', $userAgent)) {
                $os = 'Windows Phone';
                $deviceType = 'Windows Phone';
            }

            // Detectar versión de Android
            if (preg_match('/android\s+([\d.]+)/i', $userAgent, $matches)) {
                $os = 'Android ' . $matches[1];
            }

            // Detectar versión de iOS
            if (preg_match('/os\s+([\d_]+)/i', $userAgent, $matches)) {
                $os = 'iOS ' . str_replace('_', '.', $matches[1]);
            }
        }

        // Detectar navegador
        if (preg_match('/chrome\/([\d.]+)/i', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/safari\/([\d.]+)/i', $userAgent, $matches)) {
            $browser = 'Safari ' . $matches[1];
        } elseif (preg_match('/firefox\/([\d.]+)/i', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/edge\/([\d.]+)/i', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        }

        return [
            'is_mobile' => $isMobile,
            'device_type' => $deviceType,
            'os' => $os,
            'browser' => $browser,
            'user_agent' => $userAgent,
            'ip' => $ip,
        ];
    }

    /**
     * Hook cuando se monta el componente (cuando se carga la página de login)
     */
    public function mount(): void
    {
        parent::mount();

        $deviceInfo = $this->getDeviceInfo();

        // Log cuando se carga la página de login
        Log::info('[LOGIN-MOBILE-DEBUG] Página de login cargada', [
            'timestamp' => now()->toDateTimeString(),
            'is_mobile' => $deviceInfo['is_mobile'],
            'device_type' => $deviceInfo['device_type'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'ip' => $deviceInfo['ip'],
            'session_id' => session()->getId(),
            'csrf_token' => session()->token(),
            'has_session' => session()->has('_token'),
            'session_driver' => config('session.driver'),
            'session_lifetime' => config('session.lifetime'),
            'user_agent' => $deviceInfo['user_agent'],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getDocumentTypeFormComponent(),
                $this->getDocumentNumberFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getDocumentTypeFormComponent(): Component
    {
        return Select::make('document_type')
            ->label('Tipo de Documento')
            ->options([
                'DNI' => 'DNI',
                'RUC' => 'RUC',
                'CE' => 'Carné de Extranjería',
            ])
            ->required()
            ->reactive()
            ->afterStateUpdated(function ($state, callable $set) {
                // Limpiar número cuando cambia el tipo
                $set('document_number', '');
            });
    }

    protected function getDocumentNumberFormComponent(): Component
    {
        return TextInput::make('document_number')
            ->label('Número de Documento')
            ->required()
            ->placeholder(function (callable $get) {
                $type = $get('document_type');

                return match ($type) {
                    'DNI' => 'Ej: 12345678',
                    'RUC' => 'Ej: 20123456789',
                    'CE' => 'Ej: 123456789',
                    default => 'Seleccione tipo de documento',
                };
            })
            ->numeric()
            ->rule(function (callable $get) {
                $type = $get('document_type');
                
                return match ($type) {
                    'DNI' => 'regex:/^[0-9]{8}$/',
                    'RUC' => 'regex:/^[0-9]{11}$/',
                    'CE' => 'regex:/^[0-9]{9}$/',
                    default => 'numeric',
                };
            })
            ->maxLength(function (callable $get) {
                $type = $get('document_type');

                return match ($type) {
                    'DNI' => 8,
                    'RUC' => 11,
                    'CE' => 9,
                    default => 11,
                };
            })
            ->minLength(function (callable $get) {
                $type = $get('document_type');

                return match ($type) {
                    'DNI' => 8,
                    'RUC' => 11,
                    'CE' => 9,
                    default => 1,
                };
            })
            ->validationMessages([
                'regex' => function (callable $get) {
                    $type = $get('document_type');
                    
                    return match ($type) {
                        'DNI' => 'El DNI debe tener exactamente 8 números',
                        'RUC' => 'El RUC debe tener exactamente 11 números',
                        'CE' => 'El Carné de Extranjería debe tener exactamente 9 números',
                        default => 'Formato de documento inválido',
                    };
                },
                'numeric' => 'Solo se permiten números',
                'required' => 'Este campo es obligatorio',
            ])
            ->reactive()
            ->afterStateUpdated(function ($state, callable $set, $livewire) {
                // Resetear estado cuando cambia el número (ya no ocultamos el password)
                $livewire->userExists = false;
            });
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->password()
            ->revealable()
            ->placeholder('Ingrese su contraseña')
            ->visible(fn () => $this->showPasswordField)
            ->required(fn () => $this->showPasswordField);
    }

    // Método checkUser eliminado porque ahora la validación es directa

    protected function getCredentialsFromFormData(array $data): array
    {
        $deviceInfo = $this->getDeviceInfo();

        // Log al iniciar el proceso de autenticación
        Log::info('[LOGIN-MOBILE-DEBUG] Iniciando proceso de autenticación', [
            'timestamp' => now()->toDateTimeString(),
            'is_mobile' => $deviceInfo['is_mobile'],
            'device_type' => $deviceInfo['device_type'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'ip' => $deviceInfo['ip'],
            'session_id' => session()->getId(),
            'csrf_token_presente' => !empty(session()->token()),
            'has_document_type' => !empty($data['document_type']),
            'has_document_number' => !empty($data['document_number']),
            'has_password' => !empty($data['password']),
            'document_type' => $data['document_type'] ?? 'null',
        ]);

        // Validación directa con contraseña siempre presente
        if (empty($data['document_type']) || empty($data['document_number'])) {
            Log::warning('[LOGIN-MOBILE-DEBUG] Faltan datos del documento', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $deviceInfo['is_mobile'],
                'device_type' => $deviceInfo['device_type'],
            ]);

            throw ValidationException::withMessages([
                'data.document_number' => 'Por favor complete los datos del documento',
            ]);
        }

        // Usar lógica personalizada en lugar de email/password
        $authService = app(DocumentAuthService::class);
        $result = $authService->authenticateByDocument(
            $data['document_type'],
            $data['document_number'],
            $data['password'] ?? null
        );

        Log::info('[LOGIN-MOBILE-DEBUG] Resultado del servicio de autenticación', [
            'timestamp' => now()->toDateTimeString(),
            'is_mobile' => $deviceInfo['is_mobile'],
            'device_type' => $deviceInfo['device_type'],
            'success' => $result['success'],
            'action' => $result['action'] ?? 'unknown',
            'has_user' => isset($result['user']),
        ]);

        if (! $result['success']) {
            Log::warning('[LOGIN-MOBILE-DEBUG] Autenticación fallida', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $deviceInfo['is_mobile'],
                'device_type' => $deviceInfo['device_type'],
                'message' => $result['message'],
            ]);

            throw ValidationException::withMessages([
                'data.password' => $result['message'],
            ]);
        }

        switch ($result['action']) {
            case 'login':
                Log::info('[LOGIN-MOBILE-DEBUG] Login exitoso - devolviendo credenciales', [
                    'timestamp' => now()->toDateTimeString(),
                    'is_mobile' => $deviceInfo['is_mobile'],
                    'device_type' => $deviceInfo['device_type'],
                ]);

                // Retornar credenciales para el proceso normal de Filament
                return [
                    'document_type' => $data['document_type'],
                    'document_number' => $data['document_number'],
                    'password' => $data['password'],
                ];

            case 'request_password':
                Log::info('[LOGIN-MOBILE-DEBUG] Se requiere contraseña', [
                    'timestamp' => now()->toDateTimeString(),
                    'is_mobile' => $deviceInfo['is_mobile'],
                    'device_type' => $deviceInfo['device_type'],
                ]);

                throw ValidationException::withMessages([
                    'data.password' => 'Por favor, ingrese su contraseña.',
                ]);

            case 'create_password':
                Log::info('[LOGIN-MOBILE-DEBUG] Usuario necesita crear contraseña', [
                    'timestamp' => now()->toDateTimeString(),
                    'is_mobile' => $deviceInfo['is_mobile'],
                    'device_type' => $deviceInfo['device_type'],
                    'user_has_password' => !empty($result['user']->password),
                ]);

                // Solo redirigir si el usuario NO tiene contraseña válida
                if (empty($result['user']->password)) {
                    session(['pending_user_id' => $result['user']->id]);
                    $this->redirect('/auth/create-password');
                    return [];
                }
                // Si tiene contraseña, permitir login normal
                return [
                    'document_type' => $data['document_type'],
                    'document_number' => $data['document_number'],
                    'password' => $data['password'],
                ];

            default:
                Log::warning('[LOGIN-MOBILE-DEBUG] Acción desconocida', [
                    'timestamp' => now()->toDateTimeString(),
                    'is_mobile' => $deviceInfo['is_mobile'],
                    'device_type' => $deviceInfo['device_type'],
                    'action' => $result['action'] ?? 'null',
                    'message' => $result['message'],
                ]);

                throw ValidationException::withMessages([
                    'data.document_number' => $result['message'],
                ]);
        }
    }

    /**
     * Sobrescribir el método authenticate para capturar errores CSRF
     */
    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        $deviceInfo = $this->getDeviceInfo();

        try {
            Log::info('[LOGIN-MOBILE-DEBUG] Método authenticate() llamado - antes de validación', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $deviceInfo['is_mobile'],
                'device_type' => $deviceInfo['device_type'],
                'os' => $deviceInfo['os'],
                'session_id' => session()->getId(),
                'csrf_token_presente' => !empty(session()->token()),
                'request_has_token' => request()->has('_token'),
                'tokens_match' => request()->input('_token') === session()->token(),
            ]);

            // Llamar al método padre
            $result = parent::authenticate();

            Log::info('[LOGIN-MOBILE-DEBUG] Autenticación completada exitosamente', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $deviceInfo['is_mobile'],
                'device_type' => $deviceInfo['device_type'],
                'redirect' => $result ? 'yes' : 'no',
            ]);

            return $result;

        } catch (ValidationException $e) {
            Log::error('[LOGIN-MOBILE-DEBUG] ValidationException capturada', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $deviceInfo['is_mobile'],
                'device_type' => $deviceInfo['device_type'],
                'os' => $deviceInfo['os'],
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            throw $e;

        } catch (\Illuminate\Session\TokenMismatchException $e) {
            Log::error('[LOGIN-MOBILE-DEBUG] ¡TOKEN CSRF MISMATCH DETECTADO!', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $deviceInfo['is_mobile'],
                'device_type' => $deviceInfo['device_type'],
                'os' => $deviceInfo['os'],
                'browser' => $deviceInfo['browser'],
                'session_id' => session()->getId(),
                'csrf_token' => session()->token(),
                'request_token' => request()->input('_token'),
                'user_agent' => $deviceInfo['user_agent'],
                'message' => $e->getMessage(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('[LOGIN-MOBILE-DEBUG] Excepción general capturada', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $deviceInfo['is_mobile'],
                'device_type' => $deviceInfo['device_type'],
                'os' => $deviceInfo['os'],
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session_id' => session()->getId(),
            ]);

            throw $e;
        }
    }

    public function getLoginAction(): Action
    {
        return Action::make('login')
            ->label('Inicia sesión')
            ->url(filament()->getLoginUrl())
            ->color('gray');
    }

    protected function getForgotPasswordAction(): Action
    {
        return Action::make('forgotPassword')
            ->label('¿Olvidaste tu contraseña?')
            ->url(route('password.request'))
            ->color('primary')
            ->outlined();
    }

    protected function getFormActions(): array
    {
        // Mostrar el botón de "Iniciar Sesión" y "¿Olvidaste tu contraseña?"
        return [
            $this->getAuthenticateFormAction(),
            $this->getForgotPasswordAction(),
        ];
    }
}
