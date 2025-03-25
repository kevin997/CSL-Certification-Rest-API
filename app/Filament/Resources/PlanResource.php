<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Billing';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plan Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'individual_teacher' => 'Individual Teacher',
                                'business_teacher' => 'Business Teacher',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
                
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price_monthly')
                            ->label('Monthly Price')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('price_annual')
                            ->label('Annual Price')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('setup_fee')
                            ->label('One-time Setup Fee')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->default(0),
                    ])->columns(3),
                
                Forms\Components\Section::make('Features & Limits')
                    ->schema([
                        Forms\Components\Repeater::make('features')
                            ->schema([
                                Forms\Components\TextInput::make('feature')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['feature'] ?? null)
                            ->collapsible()
                            ->defaultItems(0),
                        
                        Forms\Components\Repeater::make('limits')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('value')
                                    ->required()
                                    ->numeric(),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsible()
                            ->defaultItems(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'individual_teacher' => 'success',
                        'business_teacher' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'individual_teacher' => 'Individual Teacher',
                        'business_teacher' => 'Business Teacher',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_annual')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('setup_fee')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'individual_teacher' => 'Individual Teacher',
                        'business_teacher' => 'Business Teacher',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All Plans')
                    ->trueLabel('Active Plans')
                    ->falseLabel('Inactive Plans'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Plan $plan) {
                        if ($plan->subscriptions()->exists()) {
                            $action->cancel();
                            Notification::make()
                                ->danger()
                                ->title('Cannot Delete Plan')
                                ->body('This plan has active subscriptions and cannot be deleted.')
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, array $records) {
                            foreach ($records as $record) {
                                if ($record->subscriptions()->exists()) {
                                    $action->cancel();
                                    Notification::make()
                                        ->danger()
                                        ->title('Cannot Delete Plans')
                                        ->body('One or more plans have active subscriptions and cannot be deleted.')
                                        ->send();
                                    break;
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('sort_order');
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
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
