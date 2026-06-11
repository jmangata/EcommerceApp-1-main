# Diagrammes Mermaid pour Lucidchart

## 1. Diagramme ER (Entity Relationship)

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string password
        enum role "admin|customer"
        string phone
        string address
        string city
        string postal_code
        datetime email_verified_at
    }

    categories {
        bigint id PK
        string name
        string slug UK
        text description
        string image
        boolean is_active
        integer sort_order
    }

    products {
        bigint id PK
        bigint category_id FK
        string name
        string slug UK
        text short_description
        text description
        decimal price
        decimal sale_price
        string image
        json images
        string sku UK
        integer stock_quantity
        boolean is_active
        boolean is_featured
        integer sort_order
    }

    carts {
        bigint id PK
        bigint user_id FK "unique"
    }

    cart_items {
        bigint id PK
        bigint cart_id FK
        bigint product_id FK
        integer quantity
        decimal price
    }

    orders {
        bigint id PK
        bigint user_id FK
        string order_number UK
        enum status "pending|confirmed|processing|shipped|delivered|cancelled"
        decimal subtotal
        decimal tax
        decimal shipping
        decimal total
        string shipping_name
        string shipping_email
        string shipping_phone
        string shipping_address
        string shipping_city
        string shipping_postal_code
        text customer_notes
        text admin_notes
        datetime confirmed_at
        datetime shipped_at
        datetime delivered_at
        datetime cancelled_at
    }

    order_items {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        string product_name
        string product_sku
        integer quantity
        decimal price
        decimal subtotal
    }

    users ||--o| carts : "has one"
    users ||--o{ orders : "has many"
    categories ||--o{ products : "has many"
    carts ||--o{ cart_items : "has many"
    products ||--o{ cart_items : "referenced in"
    orders ||--o{ order_items : "has many"
    products ||--o{ order_items : "referenced in"
```

## 2. Architecture en couches (C4 - Container)

```mermaid
graph TB
    subgraph "Utilisateurs"
        V[Visiteur/Client]
        A[Administrateur]
    end

    subgraph "Frontend Layer"
        BL[Blade Views<br/>TailwindCSS + Alpine.js]
        VT[Vite Build Tool]
    end

    subgraph "Application Layer"
        subgraph "Filament Panels"
            AP[Admin Panel /admin<br/>Category + Product + Order Resources]
            CP[Customer Panel /customer<br/>Espace Client]
        end

        subgraph "Controllers"
            HC[HomeController]
            PC[ProductController]
            CC[CartController]
            CHC[CheckoutController]
            PRC[ProfileController]
            AC[Auth Controllers x9]
        end
    end

    subgraph "Domain Layer"
        subgraph "Enums"
            UR[UserRole<br/>admin | customer]
            OS[OrderStatus<br/>pending → confirmed → processing → shipped → delivered]
            PS[PaymentStatus<br/>unpaid | paid | refunded | failed]
        end

        subgraph "Models"
            U[User]
            P[Product]
            CAT[Category]
            CT[Cart]
            CI[CartItem]
            O[Order]
            OI[OrderItem]
        end
    end

    subgraph "Infrastructure"
        DB[(Database<br/>SQLite/MySQL)]
        ST[Stripe API<br/>Paiement]
        FS[File Storage<br/>Images]
    end

    V --> BL
    A --> AP
    BL --> HC & PC & CC & CHC & PRC & AC
    AP --> U & P & CAT & O
    CP --> U & O
    HC & PC --> P & CAT
    CC --> CT & CI
    CHC --> O & OI & ST
    PRC & AC --> U
    U & P & CAT & CT & CI & O & OI --> DB
    P & CAT --> FS
```

## 3. Flux de commande (Order Workflow)

```mermaid
stateDiagram-v2
    [*] --> Pending: Commande créée
    Pending --> Confirmed: Admin confirme
    Pending --> Cancelled: Admin/Client annule
    Confirmed --> Processing: Préparation
    Confirmed --> Shipped: Expédition directe
    Processing --> Shipped: Expédition
    Shipped --> Delivered: Livraison confirmée
    Confirmed --> Cancelled: Annulation
    Processing --> Cancelled: Annulation

    Pending: En attente
    Confirmed: Confirmée
    Processing: En préparation
    Shipped: Expédiée
    Delivered: Livrée ✓
    Cancelled: Annulée ✗
```

## 4. Flux utilisateur (Parcours d'achat)

```mermaid
flowchart TD
    A[Visiteur arrive] --> B[Page Accueil /]
    B --> C[Catalogue /products]
    B --> D[Catégorie /categories/slug]
    C --> E[Fiche produit /products/slug]
    D --> E
    E --> F{Authentifié ?}
    F -->|Non| G[Login/Register]
    G --> F
    F -->|Oui| H[POST /cart/add - Ajout panier]
    H --> I[Panier /cart]
    I --> J[Modifier quantités]
    I --> K[Supprimer items]
    I --> L[GET /checkout]
    L --> M[Formulaire livraison]
    M --> N[POST /checkout - Paiement Stripe]
    N --> O[Stripe Checkout Session]
    O --> P{Paiement réussi ?}
    P -->|Oui| Q[/checkout/success<br/>Commande confirmée]
    P -->|Non| R[/checkout/cancel<br/>Retour panier]
    R --> I
    Q --> S[Fin - Commande en base]
```

## 5. Architecture des fichiers (Component Diagram)

```mermaid
graph LR
    subgraph "app/"
        subgraph "Enums"
            E1[UserRole]
            E2[OrderStatus]
            E3[PaymentStatus]
        end

        subgraph "Models"
            M1[User]
            M2[Product]
            M3[Category]
            M4[Cart]
            M5[CartItem]
            M6[Order]
            M7[OrderItem]
        end

        subgraph "Http/Controllers"
            C1[HomeController]
            C2[ProductController]
            C3[CartController]
            C4[CheckoutController]
            C5[ProfileController]
            C6[Auth/ x9]
        end

        subgraph "Filament/Admin/Resources"
            F1[CategoryResource]
            F2[ProductResource]
            F3[OrderResource]
        end

        subgraph "Providers"
            P1[AppServiceProvider]
            P2[AdminPanelProvider]
            P3[CustomerPanelProvider]
        end

        subgraph "View/Components"
            VC1[AppLayout]
            VC2[CardProduct]
            VC3[Navigation]
            VC4[Navheader]
        end
    end
```
