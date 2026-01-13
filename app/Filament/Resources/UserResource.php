<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Usuarios';
    }

    public static function getPluralLabel(): string
    {
        return 'Usuarios';
    }

    public static function getLabel(): string
    {
        return 'Usuario';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        Section::make('Información Personal')
                            ->description('Datos básicos del usuario')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre Completo')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: Juan Pérez García'),

                                Forms\Components\TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('usuario@ejemplo.com'),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->regex('/^(\d{9}|\d{11})$/')
                                    ->validationAttribute('teléfono')
                                    ->maxLength(11)
                                    ->placeholder('Ej: +51 999 888 777'),

                                Forms\Components\TextInput::make('password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->maxLength(255)
                                    ->placeholder('Ingrese una contraseña segura')
                                    ->revealable(),
                            ])
                            ->columns(2)
                            ->columnSpan(1),

                        Section::make('Documento de Identidad')
                            ->description('Información de identificación')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Select::make('document_type')
                                    ->label('Tipo de Documento')
                                    ->options([
                                        'DNI' => 'DNI',
                                        'CE' => 'Carné de Extranjería',
                                        'Pasaporte' => 'Pasaporte',
                                        'RUC' => 'RUC',
                                    ])
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\TextInput::make('document_number')
                                    ->label('Número de Documento')
                                    ->required()
                                    ->maxLength(20)
                                    ->placeholder('Ej: 12345678'),
                            ])
                            ->columns(2)
                            ->columnSpan(1),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Configuración de Sistema')
                            ->description('Configuraciones internas del sistema')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('c4c_internal_id')
                                    ->label('ID Interno C4C')
                                    ->maxLength(50)
                                    ->placeholder('ID automático del sistema C4C')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('c4c_uuid')
                                    ->label('UUID C4C')
                                    ->maxLength(100)
                                    ->placeholder('UUID automático del sistema C4C')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\Toggle::make('is_comodin')
                                    ->label('Usuario Comodín')
                                    ->helperText('Marcar si es un usuario de respaldo del sistema')
                                    ->default(false),
                            ])
                            ->columns(2)
                            ->columnSpan(1),

                        Section::make('Roles y Permisos')
                            ->description('Asignación de roles de acceso')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Select::make('roles')
                                    ->label('Roles')
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->placeholder('Seleccionar roles...')
                                    ->helperText('Los roles determinan los permisos del usuario'),
                            ])
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        Tables\Columns\TextColumn::make('name')
                            ->label('Usuario')
                            ->sortable()
                            ->weight('font-semibold')
                            ->size('sm'),

                        Tables\Columns\TextColumn::make('email')
                            ->color('gray')
                            ->size('sm'),
                    ])->space(1),

                    Stack::make([
                        Tables\Columns\TextColumn::make('document_type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'DNI' => 'success',
                                'CE' => 'warning',
                                'Pasaporte' => 'info',
                                'RUC' => 'danger',
                                default => 'gray',
                            }),

                        Tables\Columns\TextColumn::make('document_number')
                            ->color('gray')
                            ->searchable()
                            ->size('sm'),
                    ])->space(1),

                    Stack::make([
                        Tables\Columns\TextColumn::make('roles.name')
                            ->badge()
                            ->color('primary')
                            ->separator(',')
                            ->limitList(2)
                            ->expandableLimitedList(),

                        Tables\Columns\IconColumn::make('is_comodin')
                            ->boolean()
                            ->trueIcon('heroicon-o-shield-exclamation')
                            ->falseIcon('heroicon-o-user')
                            ->trueColor('warning')
                            ->falseColor('success')
                            ->tooltip(fn (bool $state): string => $state ? 'Usuario Comodín' : 'Usuario Regular'),
                    ])->space(1),

                    Stack::make([
                        Tables\Columns\TextColumn::make('vehicles_count')
                            ->counts('vehicles')
                            ->badge()
                            ->color('info')
                            ->suffix(' vehículos'),

                        Tables\Columns\TextColumn::make('created_at')
                            ->label('Registrado')
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->setTimezone('America/Lima')->format('d/m/Y H:i') : '')
                            ->size('sm')
                            ->color('gray'),
                    ])->space(1),
                ])->from('md'),
            ])
            ->contentGrid([
                'md' => 1,
                'lg' => 1,
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo de Documento')
                    ->options([
                        'DNI' => 'DNI',
                        'CE' => 'Carné de Extranjería',
                        'Pasaporte' => 'Pasaporte',
                        'RUC' => 'RUC',
                    ]),

                Tables\Filters\Filter::make('with_vehicles')
                    ->label('Con Vehículos')
                    ->query(fn (Builder $query): Builder => $query->has('vehicles')),

                Tables\Filters\Filter::make('comodin')
                    ->label('Usuarios Comodín')
                    ->query(fn (Builder $query): Builder => $query->where('is_comodin', true)),

                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->color('info'),
                    Tables\Actions\EditAction::make()
                        ->color('warning'),
                    Tables\Actions\DeleteAction::make()
                        ->color('danger'),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportar_excel')
                    ->label('Exportar a Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        return static::exportToExcel();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear primer usuario')
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Información del Usuario')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('name')
                                    ->label('Nombre')
                                    ->weight('bold')
                                    ->size('lg'),

                                Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable()
                                    ->icon('heroicon-o-envelope'),

                                Components\TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->copyable()
                                    ->icon('heroicon-o-phone'),

                                Components\TextEntry::make('full_document')
                                    ->label('Documento')
                                    ->copyable(),
                            ]),
                    ]),

                Components\Section::make('Información del Sistema')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('c4c_internal_id')
                                    ->label('ID C4C')
                                    ->placeholder('No asignado'),

                                Components\IconEntry::make('is_comodin')
                                    ->label('Usuario Comodín')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-shield-exclamation')
                                    ->falseIcon('heroicon-o-user'),

                                Components\TextEntry::make('vehicles_count')
                                    ->label('Vehículos')
                                    ->state(fn (User $record): int => $record->vehicles()->count())
                                    ->badge()
                                    ->color('info'),
                            ]),
                    ]),

                Components\Section::make('Roles y Permisos')
                    ->schema([
                        Components\TextEntry::make('roles.name')
                            ->label('Roles Asignados')
                            ->badge()
                            ->color('primary')
                            ->separator(','),
                    ]),

                Components\Section::make('Fechas')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('created_at')
                                    ->label('Fecha de Registro')
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->setTimezone('America/Lima')->format('d/m/Y H:i') : ''),

                                Components\TextEntry::make('updated_at')
                                    ->label('Última Actualización')
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->setTimezone('America/Lima')->format('d/m/Y H:i') : ''),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['roles']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'document_number'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($record->email) {
            $details['Email'] = $record->email;
        }

        if ($record->document_type && $record->document_number) {
            $details['Documento'] = $record->document_type.': '.$record->document_number;
        }

        return $details;
    }

    public static function exportToExcel()
    {
        // Obtener todos los usuarios con sus relaciones
        $users = User::with(['roles', 'vehicles'])->get();

        // Crear una nueva hoja de cálculo
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Configurar el título de la hoja
        $sheet->setTitle('Lista de Usuarios');

        // Definir los encabezados
        $headers = [
            'A1' => 'ID',
            'B1' => 'Nombre Completo',
            'C1' => 'Email',
            'D1' => 'Teléfono',
            'E1' => 'Tipo de Documento',
            'F1' => 'Número de Documento',
            'G1' => 'Roles',
            'H1' => 'Usuario Comodín',
            'I1' => 'ID Interno C4C',
            'J1' => 'UUID C4C',
            'K1' => 'Cantidad de Vehículos',
            'L1' => 'Fecha de Registro',
            'M1' => 'Última Actualización'
        ];

        // Escribir los encabezados
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        // Aplicar estilo a los encabezados
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:M1')->getFill()->getStartColor()->setRGB('E2E8F0');

        // Escribir los datos de los usuarios
        $row = 2;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $user->id);
            $sheet->setCellValue('B' . $row, $user->name);
            $sheet->setCellValue('C' . $row, $user->email);
            $sheet->setCellValue('D' . $row, $user->phone);
            $sheet->setCellValue('E' . $row, $user->document_type);
            $sheet->setCellValue('F' . $row, $user->document_number);
            $sheet->setCellValue('G' . $row, $user->roles->pluck('name')->join(', '));
            $sheet->setCellValue('H' . $row, $user->is_comodin ? 'Sí' : 'No');
            $sheet->setCellValue('I' . $row, $user->c4c_internal_id);
            $sheet->setCellValue('J' . $row, $user->c4c_uuid);
            $sheet->setCellValue('K' . $row, $user->vehicles->count());
            $sheet->setCellValue('L' . $row, $user->created_at ? Carbon::parse($user->created_at)->setTimezone('America/Lima')->format('d/m/Y H:i') : '');
            $sheet->setCellValue('M' . $row, $user->updated_at ? Carbon::parse($user->updated_at)->setTimezone('America/Lima')->format('d/m/Y H:i') : '');
            $row++;
        }

        // Ajustar el ancho de las columnas automáticamente
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Crear el writer
        $writer = new Xlsx($spreadsheet);

        // Generar el nombre del archivo con fecha y hora
        $filename = 'usuarios_' . date('Y-m-d_H-i-s') . '.xlsx';

        // Configurar las cabeceras para la descarga
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ];

        // Crear un archivo temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'usuarios_export');
        $writer->save($tempFile);

        // Retornar la respuesta de descarga
        return Response::download($tempFile, $filename, $headers)->deleteFileAfterSend(true);
    }
}
