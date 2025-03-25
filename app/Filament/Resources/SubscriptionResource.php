<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'Billing';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subscription Details')
                    ->schema([
                        Forms\Components\Select::make('environment_id')
                            ->relationship('environment', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('billing_cycle')
                            ->options([
                                'monthly' => 'Monthly',
                                'annual' => 'Annual',
                            ])
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'canceled' => 'Canceled',
                                'expired' => 'Expired',
                                'trial' => 'Trial',
                            ])
                            ->default('active')
                            ->required(),
                    ])->columns(2),
                
                Forms\Components\Section::make('Dates')
                    ->schema([
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial End Date'),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date')
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('End Date'),
                        Forms\Components\DateTimePicker::make('canceled_at')
                            ->label('Cancellation Date'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'credit_card' => 'Credit Card',
                                'paypal' => 'PayPal',
                                'bank_transfer' => 'Bank Transfer',
                                'other' => 'Other',
                            ]),
                        Forms\Components\KeyValue::make('payment_details')
                            ->label('Payment Details'),
                        Forms\Components\DateTimePicker::make('last_payment_at')
                            ->label('Last Payment Date'),
                        Forms\Components\DateTimePicker::make('next_payment_at')
                            ->label('Next Payment Date'),
                        Forms\Components\Toggle::make('setup_fee_paid')
                            ->label('Setup Fee Paid')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('environment.name')
                    ->label('Environment')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'monthly' => 'info',
                        'annual' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'canceled' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('setup_fee_paid')
                    ->boolean(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'trial' => 'Trial',
                        'canceled' => 'Canceled',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'annual' => 'Annual',
                    ]),
                Tables\Filters\SelectFilter::make('plan_id')
                    ->relationship('plan', 'name')
                    ->label('Plan'),
                Tables\Filters\SelectFilter::make('environment_id')
                    ->relationship('environment', 'name')
                    ->label('Environment'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
