# Matrice de trasabilitate V4

| ID | Cerință | PDF | Rută | Controller / serviciu | Tabele / queue | Test | Status |
|---|---|---|---|---|---|---|---|
| WEB-001 | Homepage CMS | 45 | `/` | `StorefrontController`, `CmsRepository` | `homepage_sections`, `settings` | `StorefrontTest` | Implementat și verificat local + producție |
| CAT-001 | Shop și filtre | 47 | `/shop` | `StorefrontController`, `ProductRepository` | `products`, `product_variants` | `CatalogTest` | Implementat și verificat local + producție |
| CAT-002 | Categorii ierarhice | 49, 93-97 | `/categorie/{slug}`, `/admin/categorii` | `AdminCatalogController` | `categories`, `product_categories` | `CategoryTreeTest` | Implementat și verificat local + producție |
| SEO-001 | Produs indexabil separat | supl. 20, 125 | `/produs/{slug}` | `ProductLifecycleService` | `products`, `url_redirects`, `sitemap_events` | `SeoLifecycleTest` | Implementat și verificat local + producție |
| COM-001 | Wishlist persistent | 83 | `/favorite`, `/api/wishlist/toggle` | `CommerceController` | `wishlists`, `wishlist_items` | `WishlistTest` | Implementat și verificat local + producție |
| UX-001 | Search modal | 85 | `/api/search` | `ApiController` | `products`, `categories` | `SearchTest` | Implementat și verificat local + producție |
| UX-002 | Add-to-cart popup | 87 | `/api/cart/items` | `ApiController`, `CartService` | `carts`, `cart_items` | `CartTest` | Implementat și verificat local + producție |
| UX-003 | Cart drawer | 89 | `/api/cart` | `ApiController`, `CartService` | `carts`, `cart_items` | `CartTest` | Implementat și verificat local + producție |
| COM-002 | Checkout PF/PJ | 55, 161 | `/checkout` | `CheckoutService` | `orders`, `order_addresses`, `stock_reservations` | `CheckoutTest` | Implementat și verificat local + producție |
| IAM-001 | Cont client | 59 | `/cont/*` | `AccountController` | `users`, `user_addresses`, `orders` | `OwnershipTest` | Implementat și verificat local + producție |
| IAM-002 | Google Auth | 133, 171 | `/auth/google/*` | `GoogleIdentityProvider` | `oauth_accounts`, `users` | `GoogleAuthTest` | Implementat și verificat local + producție |
| ORD-001 | Tracking comandă | 61, 173 | `/urmarire-comanda` | `OrderService` | `orders`, `order_status_history`, `shipments` | `TrackingAuthTest` | Implementat și verificat local + producție |
| NTF-001 | Comandă nouă | 99, 107, 146 | admin polling | `NotificationService` | `notifications`, `email_queue` | `NotificationTest` | Implementat și verificat local + producție |
| INV-001 | Facturare internă | 109, 163 | `/admin/facturi/{id}/emit` | `InternalInvoiceProvider` | `invoices`, `invoice_items`, `invoice_artifacts` | `InvoiceIdempotencyTest` | Implementat și verificat local + producție |
| INV-002 | Facturare externă | 113, 162 | `/admin/facturare/conectori` | `InvoiceProviderRegistry` | `invoice_connectors`, credentials | `ProviderHealthTest` | Implementat și verificat local + producție |
| INV-003 | Mapper șablon | 117, 165 | `/admin/facturare/sabloane/mapper` | `InvoiceTemplateService` | `invoice_template_fields` | `TemplateAllowlistTest` | Implementat și verificat local + producție |
| INV-004 | RO e-Factura | 121, 166 | `/admin/facturare/efactura` | `AnafEInvoiceConnector` | `efactura_submissions`, queue | `AnafStateTest` | Implementat și verificat local + producție |
| PAY-001 | Stripe | 129-131, 170 | `/webhooks/payments/stripe` | `StripeGateway` | `payments`, `payment_events` | `WebhookIdempotencyTest` | Implementat și verificat local + producție |
| PAY-002 | NETOPIA | 129-131, 170 | `/webhooks/payments/netopia` | `NetopiaGateway` | `payments`, `payment_events` | `WebhookIdempotencyTest` | Implementat și verificat local + producție |
| PAY-003 | Ramburs | 129, 170 | checkout | `CashOnDeliveryGateway` | `payments` | `CodPaymentTest` | Implementat și verificat local + producție |
| SHP-001 | AWB | 135-139, 172-173 | `/admin/comenzi/{id}/awb` | `ShippingProviderRegistry` | `shipments`, `awb_jobs` | `AwbIdempotencyTest` | Implementat și verificat local + producție |
| EDT-001 | Atelier landing | supl. 6 | `/atelier` | `AtelierController` | `blog_posts`, `blog_categories` | `AtelierTest` | Implementat și verificat local + producție |
| EDT-002 | Articol indexabil | supl. 7, 21 | `/atelier/{slug}` | `AtelierController`, `ArticleLifecycleService` | `blog_posts`, `sitemap_events` | `ArticleSeoTest` | Implementat și verificat local + producție |
| EDT-003 | Editor articol | supl. 9 | `/admin/atelier/{id}/edit` | `AdminEditorialController` | `blog_posts`, `blog_post_revisions` | `HtmlSanitizerTest` | Implementat și verificat local + producție |
| EDT-004 | Programare | supl. 12 | cron publish | `PublishScheduledArticlesJob` | `blog_posts`, `sitemap_events` | `ScheduledPublishTest` | Implementat și verificat local + producție |
| EDT-005 | Revizii | supl. 13 | `/admin/atelier/{id}/revisions` | `RevisionService` | `blog_post_revisions` | `RevisionRestoreTest` | Implementat și verificat local + producție |
| SEO-002 | Sitemap automat | supl. 23 | `/sitemap.xml`, `/sitemaps/*` | `SitemapService` | `sitemap_events` | `SitemapTest` | Implementat și verificat local + producție |
| SEO-003 | Redirect manager | supl. 15 | `/admin/seo/redirecturi` | `RedirectService` | `url_redirects` | `RedirectLoopTest` | Implementat și verificat local + producție |
| SEO-004 | Search Console | supl. 16 | `/admin/seo/search-console` | `SearchConsoleConnector` | `search_console_connections` | `SearchConsoleStateTest` | Implementat și verificat local + producție |
| SEO-005 | Centru indexabilitate | supl. 14 | `/admin/seo/indexabilitate` | `SeoAuditService` | `seo_audit_results` | `SeoAuditTest` | Implementat și verificat local + producție |

