# Sales Report App Implementation Plan

This plan follows `blueprint.md` and turns it into an execution checklist.

## 1. Final Stack and Project Baseline

- [x] Confirm Laravel 12 app boots correctly.
- [x] Confirm core packages are installed and compatible:
  - [x] Breeze (Blade)
  - [x] Livewire
  - [x] Spatie Permission
  - [x] Sanctum
  - [x] PostgreSQL
  - [x] Laravel Excel
- [x] Ensure environment is configured for PostgreSQL.

Deliverable: authenticated web app + API-ready backend foundation.

## 2. Livewire Setup

- [x] Install and publish Livewire config.
- [x] Add `@livewireStyles` and `@livewireScripts` in the main Blade layout.
- [x] Verify a simple Livewire component renders.

Deliverable: Livewire works end-to-end in the layout.

## 3. Module Mapping (UI Components)

- [x] `ProductManager` for product CRUD.
- [x] `SalesCreate` for multi-product sale entry.
- [x] `SalesReport` for reporting and filters.
- [x] `DashboardStats` for KPI overview.

Deliverable: component boundaries are defined before implementation.

## 4. Component Scaffolding

- [x] Generate all Livewire components.
- [x] Register routes for each component page.

Deliverable: all module pages are routable placeholders.

## 5. Database Schema and Migrations

- [x] Create `products` table:
  - [x] `name`, `price`, `stock`, timestamps.
- [x] Create `sales` table:
  - [x] `user_id`, `customer_name`, `date`, `total_amount`, `commission_amount`, timestamps.
- [x] Create `sale_items` table:
  - [x] `sale_id`, `product_id`, `qty`, `price`, `subtotal`, timestamps.
- [x] Apply foreign keys with cascade delete.
- [x] Run migrations.

Deliverable: normalized schema for products, sales, and sale lines.

## 6. Eloquent Models and Relationships

- [x] Implement `Product` model:
  - [x] fillable and casts.
  - [x] `saleItems()` relationship.
- [x] Implement `Sale` model:
  - [x] fillable and casts.
  - [x] `user()` and `items()` relationships.
- [x] Implement `SaleItem` model:
  - [x] fillable and casts.
  - [x] `sale()` and `product()` relationships.

Deliverable: model layer aligned with schema and business flow.

## 7. Product Module (Livewire CRUD)

- [x] Build create/update flow with validation.
- [x] Build edit flow that loads selected row.
- [x] Build delete flow with guard:
  - [x] block delete if product has existing `saleItems`.
- [x] Add pagination and flash messaging.

Deliverable: full product CRUD in `ProductManager`.

## 8. Sales Entry (Multi-Item)

- [x] Build dynamic line-item form in `SalesCreate`.
- [x] Add validation for customer, product, and quantity.
- [x] Add add/remove item behavior.
- [x] Submit to service layer (not direct heavy logic in component).

Deliverable: sales form supports multiple products per transaction.

## 9. Service Layer

- [x] Create `SaleService`.
- [x] Implement `createSale()` with transaction:
  - [x] create sale header.
  - [x] validate stock per item.
  - [x] create sale items.
  - [x] decrement stock.
  - [x] calculate and store totals/commission.
- [x] Implement `voidSale()` with transaction:
  - [x] restore stock.
  - [x] remove items and sale.

Deliverable: business rules are centralized and transactional.

## 10. Commission Logic

- [x] Create `CommissionService`.
- [x] Implement base commission formula (`5%` of total).
- [x] Keep service isolated for future rule expansion.

Deliverable: reusable commission strategy abstraction.

## 11. Reporting and Chart

- [x] Build date-range filters in `SalesReport`.
- [x] Query and paginate filtered sales data.
- [x] Build computed chart dataset (daily totals).
- [x] Add Chart.js script in layout.
- [x] Render chart in report view.

Deliverable: interactive report with table + daily sales chart.

## 12. Export

- [x] Create `SalesExport` class using query + mapping + headings.
- [x] Add export action in `SalesReport`.
- [x] Download as `sales-report.xlsx` for selected date range.

Deliverable: filtered Excel export from report page.

## 13. Authorization and Roles

- [x] Protect routes with `auth` middleware.
- [x] Gate report access with role/permission checks.
- [x] Restrict report UI to authorized users.

Deliverable: role-aware access control for sensitive pages.

## 14. Dashboard Stats

- [x] Implement `DashboardStats` metrics:
  - [x] today sales.
  - [x] monthly sales.
  - [x] total products.
  - [x] low stock list.
  - [x] top products.
  - [x] recent sales.

Deliverable: operational dashboard for quick business insight.

## 15. Shared Flash Messages

- [x] Create `resources/views/partials/flash-messages.blade.php`.
- [x] Include partial in Livewire module views.

Deliverable: consistent success/error feedback across UI.

## 16. Seeder and Sample Data

- [x] Create `ProductSeeder` with starter products.
- [x] Register seeder in database seeding flow if needed.
- [x] Seed local/dev database.

Deliverable: realistic sample catalog for testing flows.

## 17. Testing Plan (Required)

- [x] Feature test: product CRUD and validation.
- [x] Feature test: cannot delete product tied to sale item.
- [x] Feature test: sales create with multiple items.
- [x] Feature test: stock is decremented after sale.
- [x] Feature test: insufficient stock fails gracefully.
- [x] Feature test: sales report date filter.
- [x] Feature test: export endpoint/action returns file.
- [x] Feature test: role/permission gating for reports.
- [x] Unit test: `CommissionService::calculate()`.
- [x] Unit/feature test: `SaleService` transaction behavior.

Deliverable: coverage for core business and UI behavior.

## 18. Execution Order

1. Stack verification and Livewire setup.
2. Migrations and models.
3. Service layer and commission logic.
4. Product module.
5. Sales form.
6. Reports, chart, and export.
7. Dashboard.
8. Authorization.
9. Seeder.
10. Automated tests and final QA pass.

## 19. Done Criteria

- [x] All pages route and render without errors.
- [x] Sales flow is transactional and stock-safe.
- [x] Reporting and export work for date ranges.
- [x] Access control is enforced for protected modules.
- [x] Tests for critical flows pass.
