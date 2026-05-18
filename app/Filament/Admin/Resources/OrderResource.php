<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Enums\OrderStatus;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationLabel(): string
    {
        return 'Commandes';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventes';
    }

    public static function getModelLabel(): string
    {
        return 'commande';
    }

    public static function getPluralModelLabel(): string
    {
        return 'commandes';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informations commande')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Numéro de commande')
                            ->disabled(),

                        Forms\Components\Select::make('user_id')
                            ->label('Client')
                            ->relationship('user', 'name')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->options(OrderStatus::toArray())
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notes admin')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Montants')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Sous-total')
                            ->prefix('€')
                            ->disabled(),

                        Forms\Components\TextInput::make('tax')
                            ->label('TVA')
                            ->prefix('€')
                            ->disabled(),

                        Forms\Components\TextInput::make('shipping')
                            ->label('Livraison')
                            ->prefix('€')
                            ->disabled(),

                        Forms\Components\TextInput::make('total')
                            ->label('Total')
                            ->prefix('€')
                            ->disabled(),
                    ])
                    ->columns(4),

                Section::make('Adresse de livraison')
                    ->schema([
                        Forms\Components\TextInput::make('shipping_name')
                            ->label('Nom')
                            ->disabled(),

                        Forms\Components\TextInput::make('shipping_email')
                            ->label('Email')
                            ->disabled(),

                        Forms\Components\TextInput::make('shipping_phone')
                            ->label('Téléphone')
                            ->disabled(),

                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Adresse')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('shipping_postal_code')
                            ->label('Code postal')
                            ->disabled(),

                        Forms\Components\TextInput::make('shipping_city')
                            ->label('Ville')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('N° Commande')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (OrderStatus $state) => $state->color())
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label()),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('shipping_city')
                    ->label('Ville')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(OrderStatus::toArray()),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Du'),
                        Forms\Components\DatePicker::make('until')->label('Au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirmer')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (Order $record) => $record->status === OrderStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->update([
                        'status' => OrderStatus::CONFIRMED,
                        'confirmed_at' => now(),
                    ])),

                Action::make('ship')
                    ->label('Expédier')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::CONFIRMED, OrderStatus::PROCESSING]))
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->update([
                        'status' => OrderStatus::SHIPPED,
                        'shipped_at' => now(),
                    ])),

                Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) => $record->status->isEditable())
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->update([
                        'status' => OrderStatus::CANCELLED,
                        'cancelled_at' => now(),
                    ])),

                EditAction::make()->label('Détails'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
