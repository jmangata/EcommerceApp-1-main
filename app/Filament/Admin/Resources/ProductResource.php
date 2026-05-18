<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Category;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cube';
    }

    public static function getNavigationLabel(): string
    {
        return 'Produits';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalogue';
    }

    public static function getModelLabel(): string
    {
        return 'produit';
    }

    public static function getPluralModelLabel(): string
    {
        return 'produits';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Informations générales')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nom du produit')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) =>
                                        $set('slug', Str::slug($state))
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('category_id')
                                    ->label('Catégorie')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Textarea::make('short_description')
                                    ->label('Description courte')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('description')
                                    ->label('Description complète')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Prix & Stock')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Prix (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required(),

                                Forms\Components\TextInput::make('sale_price')
                                    ->label('Prix promo (€)')
                                    ->numeric()
                                    ->prefix('€'),

                                Forms\Components\TextInput::make('stock_quantity')
                                    ->label('Stock')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Ordre d\'affichage')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(2),

                Group::make()
                    ->schema([
                        Section::make('Image')
                            ->schema([
                                Forms\Components\FileUpload::make('image')
                                    ->label('Image principale')
                                    ->image()
                                    ->directory('products')
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('1:1'),
                            ]),

                        Section::make('Visibilité')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Produit actif')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Produit vedette')
                                    ->default(false),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->square()
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Prix')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Prix promo')
                    ->money('EUR')
                    ->sortable()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === 0 => 'danger',
                        $state < 10 => 'warning',
                        default => 'success',
                    })
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Vedette')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actif'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Vedette'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
