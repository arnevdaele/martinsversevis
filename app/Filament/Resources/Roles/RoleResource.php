<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Support\PermissionMatrix;
use App\Filament\Support\Resource;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Rollen & rechten';

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'rollen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->maxLength(120)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('guard_name', Permissions::GUARD))
                        ->notIn([Permissions::SUPER_ADMIN_ROLE]),
                ]),
            Section::make('Rechten')
                ->description('Grijze vakjes zijn rechten die je zelf niet hebt; die kan je niet toekennen.')
                ->columnSpanFull()
                ->schema([PermissionMatrix::fields()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('guard_name', Permissions::GUARD)->withCount(['permissions', 'users']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->weight('medium')
                    ->description(fn (Role $record) => $record->name === Permissions::SUPER_ADMIN_ROLE
                        ? 'Heeft altijd alle rechten. Niet aanpasbaar.'
                        : null),
                TextColumn::make('permissions_count')
                    ->label('Rechten')
                    ->state(fn (Role $record) => $record->name === Permissions::SUPER_ADMIN_ROLE ? 'Alle' : $record->permissions_count)
                    ->alignCenter(),
                TextColumn::make('users_count')->label('Beheerders')->alignCenter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Beheerders met deze rol verliezen de bijhorende rechten.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
