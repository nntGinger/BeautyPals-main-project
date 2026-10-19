-- =====================================================================
-- Beauty Marketplace - DDL day du (MySQL 8.0, InnoDB, utf8mb4)
-- 34 bang: 27 bang loi (ke hoach 4 tuan) + 7 bang mo rong (Should)
-- Quy uoc: tien = BIGINT (dong); trang thai = VARCHAR + CHECK;
--          bang append-only chi co created_at.
-- Thu tu tao bang theo phu thuoc khoa ngoai.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- NHOM 1. NGUOI DUNG VA VENDOR
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(120)    NOT NULL,
  email             VARCHAR(190)    NOT NULL,
  email_verified_at TIMESTAMP       NULL,
  password          VARCHAR(255)    NOT NULL,            -- bcrypt/argon hash, khong luu mat khau goc
  phone             VARCHAR(20)     NULL,
  role              VARCHAR(20)     NOT NULL DEFAULT 'customer',
  status            VARCHAR(20)     NOT NULL DEFAULT 'active',
  remember_token    VARCHAR(100)    NULL,
  last_login_at     TIMESTAMP       NULL,
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  CONSTRAINT ck_users_role   CHECK (role   IN ('customer','vendor','admin')),
  CONSTRAINT ck_users_status CHECK (status IN ('active','locked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendors (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id    BIGINT UNSIGNED NOT NULL,
  store_name       VARCHAR(150)    NOT NULL,
  tax_code         VARCHAR(20)     NOT NULL,
  phone            VARCHAR(20)     NULL,
  address          VARCHAR(255)    NULL,
  license_file     VARCHAR(255)    NOT NULL,             -- duong dan tren disk private
  status           VARCHAR(20)     NOT NULL DEFAULT 'pending',
  reject_reason    VARCHAR(255)    NULL,
  suspended_reason VARCHAR(255)    NULL,
  approved_by      BIGINT UNSIGNED NULL,
  approved_at      TIMESTAMP       NULL,
  created_at       TIMESTAMP       NULL,
  updated_at       TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vendors_owner (owner_user_id),           -- mot user chi so huu mot vendor
  UNIQUE KEY uq_vendors_store_name (store_name),
  UNIQUE KEY uq_vendors_tax_code (tax_code),
  KEY idx_vendors_status (status),
  CONSTRAINT fk_vendors_owner    FOREIGN KEY (owner_user_id) REFERENCES users (id),
  CONSTRAINT fk_vendors_approver FOREIGN KEY (approved_by)   REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_vendors_status   CHECK (status IN ('pending','approved','rejected','suspended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addresses (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  receiver        VARCHAR(120)    NOT NULL,
  phone           VARCHAR(20)     NOT NULL,
  line            VARCHAR(255)    NOT NULL,
  ward            VARCHAR(100)    NOT NULL,
  district        VARCHAR(100)    NOT NULL,
  city            VARCHAR(100)    NOT NULL,
  is_default      TINYINT(1)      NOT NULL DEFAULT 0,
  -- cot sinh tu dong: chi co gia tri khi la dia chi mac dinh, UNIQUE dam bao moi user toi da 1 dia chi mac dinh
  default_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_default = 1 THEN user_id ELSE NULL END) STORED,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_addresses_one_default (default_user_id),
  KEY idx_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- NHOM 2. CATALOG
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS brands (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120)    NOT NULL,
  created_at TIMESTAMP       NULL,
  updated_at TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brands_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id  BIGINT UNSIGNED NULL,
  name       VARCHAR(120)    NOT NULL,
  slug       VARCHAR(150)    NOT NULL,
  created_at TIMESTAMP       NULL,
  updated_at TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent (parent_id),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skin_types (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(60)     NOT NULL,
  created_at TIMESTAMP       NULL,
  updated_at TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skin_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id     BIGINT UNSIGNED NOT NULL,
  brand_id      BIGINT UNSIGNED NULL,
  category_id   BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(200)    NOT NULL,
  slug          VARCHAR(220)    NOT NULL,
  description   TEXT            NULL,
  status        VARCHAR(20)     NOT NULL DEFAULT 'draft',
  reject_reason VARCHAR(255)    NULL,
  moderated_by  BIGINT UNSIGNED NULL,
  moderated_at  TIMESTAMP       NULL,
  created_at    TIMESTAMP       NULL,
  updated_at    TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_vendor_status (vendor_id, status),
  KEY idx_products_category_status (category_id, status),
  KEY idx_products_brand (brand_id),
  KEY idx_products_name (name),
  CONSTRAINT fk_products_vendor    FOREIGN KEY (vendor_id)    REFERENCES vendors (id),
  CONSTRAINT fk_products_brand     FOREIGN KEY (brand_id)     REFERENCES brands (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_category  FOREIGN KEY (category_id)  REFERENCES categories (id),
  CONSTRAINT fk_products_moderator FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_products_status    CHECK (status IN ('draft','pending','approved','rejected','hidden'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_skin_type (
  product_id   BIGINT UNSIGNED NOT NULL,
  skin_type_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, skin_type_id),
  KEY idx_pst_skin_type (skin_type_id),
  CONSTRAINT fk_pst_product   FOREIGN KEY (product_id)   REFERENCES products (id)   ON DELETE CASCADE,
  CONSTRAINT fk_pst_skin_type FOREIGN KEY (skin_type_id) REFERENCES skin_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skus (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id   BIGINT UNSIGNED NOT NULL,
  sku_code     VARCHAR(60)     NOT NULL,
  variant_name VARCHAR(100)    NOT NULL,
  price        BIGINT UNSIGNED NOT NULL,                 -- dong
  size         VARCHAR(50)     NULL,
  is_active    TINYINT(1)      NOT NULL DEFAULT 1,
  created_at   TIMESTAMP       NULL,
  updated_at   TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skus_code (sku_code),
  KEY idx_skus_product_active (product_id, is_active),
  KEY idx_skus_price (price),
  CONSTRAINT fk_skus_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT ck_skus_price   CHECK (price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  path       VARCHAR(255)    NOT NULL,
  sort       SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- 0 la anh chinh
  created_at TIMESTAMP       NULL,
  updated_at TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_product_images_product_sort (product_id, sort),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredients (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inci_name    VARCHAR(150)    NOT NULL,
  display_name VARCHAR(150)    NOT NULL,
  function     VARCHAR(255)    NULL,
  created_at   TIMESTAMP       NULL,
  updated_at   TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ingredients_inci (inci_name),
  KEY idx_ingredients_display (display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredient_aliases (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingredient_id BIGINT UNSIGNED NOT NULL,
  alias         VARCHAR(150)    NOT NULL,
  created_at    TIMESTAMP       NULL,
  updated_at    TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ingredient_aliases_alias (alias),        -- collation _ci: khong phan biet hoa thuong
  KEY idx_ingredient_aliases_ingredient (ingredient_id),
  CONSTRAINT fk_ingredient_aliases_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_ingredients (
  product_id    BIGINT UNSIGNED NOT NULL,
  ingredient_id BIGINT UNSIGNED NOT NULL,
  position      SMALLINT UNSIGNED NOT NULL,              -- vi tri trong danh sach INCI (1 = nhieu nhat)
  concentration DECIMAL(5,2)    NULL,                    -- % neu nha san xuat cong bo
  PRIMARY KEY (product_id, ingredient_id),
  UNIQUE KEY uq_product_ingredients_position (product_id, position),
  KEY idx_product_ingredients_ingredient (ingredient_id),
  CONSTRAINT fk_pi_product    FOREIGN KEY (product_id)    REFERENCES products (id)    ON DELETE CASCADE,
  CONSTRAINT fk_pi_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients (id),
  CONSTRAINT ck_pi_concentration CHECK (concentration IS NULL OR (concentration > 0 AND concentration <= 100)),
  CONSTRAINT ck_pi_position      CHECK (position >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- NHOM 3. TON KHO THEO LO
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS batches (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku_id       BIGINT UNSIGNED NOT NULL,
  batch_no     VARCHAR(60)     NOT NULL,
  mfg_date     DATE            NOT NULL,
  exp_date     DATE            NOT NULL,
  qty_received INT UNSIGNED    NOT NULL,
  qty_on_hand  INT UNSIGNED    NOT NULL,                 -- ton vat ly con trong kho
  qty_reserved INT UNSIGNED    NOT NULL DEFAULT 0,       -- dang giu cho don chua xuat
  status       VARCHAR(20)     NOT NULL DEFAULT 'active',
  created_at   TIMESTAMP       NULL,
  updated_at   TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_batches_sku_batch (sku_id, batch_no),
  KEY idx_batches_fefo (sku_id, status, exp_date),       -- phuc vu chon lo FEFO
  CONSTRAINT fk_batches_sku       FOREIGN KEY (sku_id) REFERENCES skus (id),
  CONSTRAINT ck_batches_status    CHECK (status IN ('active','quarantined','expired')),
  CONSTRAINT ck_batches_dates     CHECK (exp_date > mfg_date),
  CONSTRAINT ck_batches_received  CHECK (qty_received > 0),
  CONSTRAINT ck_batches_reserved  CHECK (qty_reserved <= qty_on_hand)   -- khong bao gio giu qua ton
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- NHOM 4. GIO HANG VA DON HANG (kem order_item_allocations, stock_movements
--         vi chung tham chieu order_items)
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS carts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP       NULL,
  updated_at TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_user (user_id),                    -- mot gio cho moi user
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id    BIGINT UNSIGNED NOT NULL,
  sku_id     BIGINT UNSIGNED NOT NULL,
  qty        INT UNSIGNED    NOT NULL,
  created_at TIMESTAMP       NULL,
  updated_at TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_items_cart_sku (cart_id, sku_id),
  KEY idx_cart_items_sku (sku_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_sku  FOREIGN KEY (sku_id)  REFERENCES skus (id)  ON DELETE CASCADE,
  CONSTRAINT ck_cart_items_qty  CHECK (qty >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(40)     NOT NULL,
  type         VARCHAR(10)     NOT NULL,
  value        BIGINT UNSIGNED NOT NULL,                 -- % neu percent, dong neu fixed
  max_discount BIGINT UNSIGNED NULL,
  min_order    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  starts_at    TIMESTAMP       NOT NULL,
  ends_at      TIMESTAMP       NOT NULL,
  usage_limit  INT UNSIGNED    NULL,                     -- NULL = khong gioi han
  used_count   INT UNSIGNED    NOT NULL DEFAULT 0,
  status       VARCHAR(20)     NOT NULL DEFAULT 'active',
  created_at   TIMESTAMP       NULL,
  updated_at   TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vouchers_code (code),
  KEY idx_vouchers_active (status, starts_at, ends_at),
  CONSTRAINT ck_vouchers_type   CHECK (type IN ('percent','fixed')),
  CONSTRAINT ck_vouchers_status CHECK (status IN ('active','inactive')),
  CONSTRAINT ck_vouchers_window CHECK (ends_at > starts_at),
  CONSTRAINT ck_vouchers_value  CHECK (value > 0 AND (type = 'fixed' OR value <= 100)),
  CONSTRAINT ck_vouchers_usage  CHECK (usage_limit IS NULL OR used_count <= usage_limit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_code      VARCHAR(30)     NOT NULL,
  customer_id     BIGINT UNSIGNED NOT NULL,
  receiver        VARCHAR(120)    NOT NULL,              -- snapshot
  phone           VARCHAR(20)     NOT NULL,              -- snapshot
  address_text    VARCHAR(500)    NOT NULL,              -- snapshot
  subtotal        BIGINT UNSIGNED NOT NULL,
  shipping_total  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  discount_total  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  grand_total     BIGINT UNSIGNED NOT NULL,
  payment_method  VARCHAR(20)     NOT NULL DEFAULT 'cod',
  payment_status  VARCHAR(20)     NOT NULL DEFAULT 'pending',
  status          VARCHAR(20)     NOT NULL DEFAULT 'pending',   -- suy ra tu vendor_orders
  voucher_id      BIGINT UNSIGNED NULL,
  idempotency_key CHAR(36)        NOT NULL,              -- chong dat hang trung khi gui lai form
  placed_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_code (order_code),
  UNIQUE KEY uq_orders_idempotency (idempotency_key),
  KEY idx_orders_customer_placed (customer_id, placed_at),
  KEY idx_orders_status (status),
  CONSTRAINT fk_orders_customer       FOREIGN KEY (customer_id) REFERENCES users (id),
  CONSTRAINT fk_orders_voucher        FOREIGN KEY (voucher_id)  REFERENCES vouchers (id) ON DELETE SET NULL,
  CONSTRAINT ck_orders_payment_method CHECK (payment_method IN ('cod')),
  CONSTRAINT ck_orders_payment_status CHECK (payment_status IN ('pending','paid','failed','refunded')),
  CONSTRAINT ck_orders_status         CHECK (status IN ('pending','processing','completed','cancelled')),
  CONSTRAINT ck_orders_total          CHECK (grand_total = subtotal + shipping_total - discount_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendor_orders (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id          BIGINT UNSIGNED NOT NULL,
  vendor_id         BIGINT UNSIGNED NOT NULL,
  vo_code           VARCHAR(30)     NOT NULL,
  status            VARCHAR(20)     NOT NULL DEFAULT 'pending',
  subtotal          BIGINT UNSIGNED NOT NULL,
  shipping_fee      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  discount          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total             BIGINT UNSIGNED NOT NULL,
  commission_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- tong hoa hong cac dong hang (snapshot)
  tracking_code     VARCHAR(60)     NULL,
  confirmed_at      TIMESTAMP       NULL,
  shipped_at        TIMESTAMP       NULL,
  delivered_at      TIMESTAMP       NULL,
  completed_at      TIMESTAMP       NULL,
  cancel_reason     VARCHAR(255)    NULL,
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vendor_orders_code (vo_code),
  UNIQUE KEY uq_vendor_orders_order_vendor (order_id, vendor_id),   -- moi vendor mot don con trong moi don cha
  KEY idx_vendor_orders_vendor_status (vendor_id, status, created_at),
  KEY idx_vendor_orders_autocomplete (status, delivered_at),        -- lenh orders:complete
  KEY idx_vendor_orders_settlement (vendor_id, status, completed_at),
  CONSTRAINT fk_vendor_orders_order  FOREIGN KEY (order_id)  REFERENCES orders (id),
  CONSTRAINT fk_vendor_orders_vendor FOREIGN KEY (vendor_id) REFERENCES vendors (id),
  CONSTRAINT ck_vendor_orders_status CHECK (status IN ('pending','confirmed','packed','shipped','delivered','completed','cancelled','rejected')),
  CONSTRAINT ck_vendor_orders_total  CHECK (total = subtotal + shipping_fee - discount),
  CONSTRAINT ck_vendor_orders_commission CHECK (commission_amount <= subtotal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_order_id   BIGINT UNSIGNED NOT NULL,
  sku_id            BIGINT UNSIGNED NOT NULL,
  product_name      VARCHAR(200)    NOT NULL,            -- snapshot
  sku_code          VARCHAR(60)     NOT NULL,            -- snapshot
  unit_price        BIGINT UNSIGNED NOT NULL,            -- snapshot
  qty               INT UNSIGNED    NOT NULL,
  line_total        BIGINT UNSIGNED NOT NULL,
  commission_rate   DECIMAL(5,2)    NOT NULL,            -- snapshot ty le hoa hong luc dat
  commission_amount BIGINT UNSIGNED NOT NULL,            -- snapshot, lam tron half-up
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_vendor_order (vendor_order_id),
  KEY idx_order_items_sku (sku_id),
  CONSTRAINT fk_order_items_vendor_order FOREIGN KEY (vendor_order_id) REFERENCES vendor_orders (id),
  CONSTRAINT fk_order_items_sku          FOREIGN KEY (sku_id)          REFERENCES skus (id),
  CONSTRAINT ck_order_items_qty          CHECK (qty >= 1),
  CONSTRAINT ck_order_items_line_total   CHECK (line_total = unit_price * qty),
  CONSTRAINT ck_order_items_rate         CHECK (commission_rate >= 0 AND commission_rate <= 100),
  CONSTRAINT ck_order_items_commission   CHECK (commission_amount <= line_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_item_allocations (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_item_id BIGINT UNSIGNED NOT NULL,
  batch_id      BIGINT UNSIGNED NOT NULL,
  qty           INT UNSIGNED    NOT NULL,
  status        VARCHAR(20)     NOT NULL DEFAULT 'held',
  expires_at    TIMESTAMP       NULL,                    -- han giu cho (neu co co che het han)
  created_at    TIMESTAMP       NULL,
  updated_at    TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_allocations_item_batch (order_item_id, batch_id),
  KEY idx_allocations_batch_status (batch_id, status),
  KEY idx_allocations_expiry (status, expires_at),
  CONSTRAINT fk_allocations_item  FOREIGN KEY (order_item_id) REFERENCES order_items (id),
  CONSTRAINT fk_allocations_batch FOREIGN KEY (batch_id)      REFERENCES batches (id),
  CONSTRAINT ck_allocations_qty    CHECK (qty > 0),
  CONSTRAINT ck_allocations_status CHECK (status IN ('held','committed','released'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id   BIGINT UNSIGNED NOT NULL,
  type       VARCHAR(20)     NOT NULL,
  qty        INT             NOT NULL,                   -- luon > 0, rieng 'adjust' co dau (+/-)
  ref_type   VARCHAR(40)     NULL,                       -- vd: order_item_allocation, batch, return_item
  ref_id     BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,                       -- NULL = he thong
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stock_movements_batch (batch_id, created_at),
  KEY idx_stock_movements_ref (ref_type, ref_id),
  CONSTRAINT fk_stock_movements_batch FOREIGN KEY (batch_id)   REFERENCES batches (id),
  CONSTRAINT fk_stock_movements_user  FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_stock_movements_type  CHECK (type IN ('receive','reserve','release','deduct','adjust','return')),
  CONSTRAINT ck_stock_movements_qty   CHECK (qty <> 0 AND (type = 'adjust' OR qty > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_order_id BIGINT UNSIGNED NOT NULL,
  from_status     VARCHAR(20)     NULL,                  -- NULL khi tao don
  to_status       VARCHAR(20)     NOT NULL,
  changed_by      BIGINT UNSIGNED NULL,                  -- NULL = he thong (orders:complete)
  note            VARCHAR(255)    NULL,
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_osh_vendor_order (vendor_order_id, created_at),
  CONSTRAINT fk_osh_vendor_order FOREIGN KEY (vendor_order_id) REFERENCES vendor_orders (id),
  CONSTRAINT fk_osh_user         FOREIGN KEY (changed_by)      REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_osh_to_status    CHECK (to_status IN ('pending','confirmed','packed','shipped','delivered','completed','cancelled','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   BIGINT UNSIGNED NOT NULL,
  method     VARCHAR(20)     NOT NULL DEFAULT 'cod',
  amount     BIGINT UNSIGNED NOT NULL,
  status     VARCHAR(20)     NOT NULL DEFAULT 'pending',
  txn_ref    VARCHAR(100)    NULL,                       -- NULL voi COD; UNIQUE chong ghi nhan hai lan
  paid_at    TIMESTAMP       NULL,
  created_at TIMESTAMP       NULL,
  updated_at TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_txn_ref (txn_ref),
  KEY idx_payments_order (order_id),
  CONSTRAINT fk_payments_order  FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT ck_payments_method CHECK (method IN ('cod')),
  CONSTRAINT ck_payments_status CHECK (status IN ('pending','paid','failed','refunded'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- NHOM 5. HOA HONG, DOI SOAT, NHAT KY
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS commission_rules (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id    BIGINT UNSIGNED NULL,                   -- NULL = ty le mac dinh toan san
  rate           DECIMAL(5,2)    NOT NULL,
  effective_from DATE            NOT NULL,
  is_active      TINYINT(1)      NOT NULL DEFAULT 1,
  category_key   BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(category_id, 0)) STORED,
  created_at     TIMESTAMP       NULL,
  updated_at     TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commission_rules_scope (category_key, effective_from),
  KEY idx_commission_rules_lookup (category_id, is_active, effective_from),
  CONSTRAINT fk_commission_rules_category FOREIGN KEY (category_id) REFERENCES categories (id),
  CONSTRAINT ck_commission_rules_rate     CHECK (rate >= 0 AND rate <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settlements (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id        BIGINT UNSIGNED NOT NULL,
  period_start     DATE            NOT NULL,
  period_end       DATE            NOT NULL,
  gross_sales      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  commission_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
  refund_total     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  adjustment       BIGINT          NOT NULL DEFAULT 0,   -- co dau
  net_payout       BIGINT          NOT NULL DEFAULT 0,   -- co dau
  status           VARCHAR(20)     NOT NULL DEFAULT 'draft',
  finalized_by     BIGINT UNSIGNED NULL,
  paid_at          TIMESTAMP       NULL,
  created_at       TIMESTAMP       NULL,
  updated_at       TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settlements_vendor_period (vendor_id, period_start, period_end),
  KEY idx_settlements_status (status),
  CONSTRAINT fk_settlements_vendor FOREIGN KEY (vendor_id)    REFERENCES vendors (id),
  CONSTRAINT fk_settlements_user   FOREIGN KEY (finalized_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_settlements_period CHECK (period_end >= period_start),
  CONSTRAINT ck_settlements_status CHECK (status IN ('draft','finalized','paid')),
  CONSTRAINT ck_settlements_net    CHECK (net_payout = gross_sales - commission_total - refund_total + adjustment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settlement_lines (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  settlement_id   BIGINT UNSIGNED NOT NULL,
  vendor_order_id BIGINT UNSIGNED NOT NULL,
  gross           BIGINT UNSIGNED NOT NULL,
  commission      BIGINT UNSIGNED NOT NULL,
  refund          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  net             BIGINT          NOT NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settlement_lines_vendor_order (vendor_order_id),   -- moi don chi vao mot ky
  KEY idx_settlement_lines_settlement (settlement_id),
  CONSTRAINT fk_settlement_lines_settlement   FOREIGN KEY (settlement_id)   REFERENCES settlements (id) ON DELETE CASCADE,
  CONSTRAINT fk_settlement_lines_vendor_order FOREIGN KEY (vendor_order_id) REFERENCES vendor_orders (id),
  CONSTRAINT ck_settlement_lines_net          CHECK (net = gross - commission - refund)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,                      -- NULL = he thong
  action      VARCHAR(60)     NOT NULL,
  entity_type VARCHAR(40)     NOT NULL,
  entity_id   BIGINT UNSIGNED NULL,
  old_values  JSON            NULL,
  new_values  JSON            NULL,
  ip          VARCHAR(45)     NULL,                      -- du cho IPv6
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_actor (actor_id, created_at),
  KEY idx_audit_action (action, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voucher_redemptions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  voucher_id BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  order_id   BIGINT UNSIGNED NOT NULL,
  amount     BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_voucher_redemptions_order (voucher_id, order_id),
  KEY idx_voucher_redemptions_user (user_id, voucher_id),
  CONSTRAINT fk_vr_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers (id),
  CONSTRAINT fk_vr_user    FOREIGN KEY (user_id)    REFERENCES users (id),
  CONSTRAINT fk_vr_order   FOREIGN KEY (order_id)   REFERENCES orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_item_id BIGINT UNSIGNED NOT NULL,
  product_id    BIGINT UNSIGNED NOT NULL,
  customer_id   BIGINT UNSIGNED NOT NULL,
  rating        TINYINT UNSIGNED NOT NULL,
  comment       TEXT            NULL,
  status        VARCHAR(20)     NOT NULL DEFAULT 'visible',
  created_at    TIMESTAMP       NULL,
  updated_at    TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_order_item (order_item_id),      -- mot dong hang danh gia mot lan
  KEY idx_reviews_product (product_id, status),
  KEY idx_reviews_customer (customer_id),
  CONSTRAINT fk_reviews_order_item FOREIGN KEY (order_item_id) REFERENCES order_items (id),
  CONSTRAINT fk_reviews_product    FOREIGN KEY (product_id)    REFERENCES products (id),
  CONSTRAINT fk_reviews_customer   FOREIGN KEY (customer_id)   REFERENCES users (id),
  CONSTRAINT ck_reviews_rating     CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT ck_reviews_status     CHECK (status IN ('visible','hidden'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlists (
  user_id    BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, product_id),
  KEY idx_wishlists_product (product_id),
  CONSTRAINT fk_wishlists_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
  CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS return_requests (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_order_id BIGINT UNSIGNED NOT NULL,
  customer_id     BIGINT UNSIGNED NOT NULL,
  reason          VARCHAR(500)    NOT NULL,
  status          VARCHAR(20)     NOT NULL DEFAULT 'requested',
  refund_amount   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  handled_by      BIGINT UNSIGNED NULL,
  handled_at      TIMESTAMP       NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_return_requests_vendor_order (vendor_order_id),
  KEY idx_return_requests_status (status, created_at),
  KEY idx_return_requests_customer (customer_id),
  CONSTRAINT fk_rr_vendor_order FOREIGN KEY (vendor_order_id) REFERENCES vendor_orders (id),
  CONSTRAINT fk_rr_customer     FOREIGN KEY (customer_id)     REFERENCES users (id),
  CONSTRAINT fk_rr_handler      FOREIGN KEY (handled_by)      REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_rr_status       CHECK (status IN ('requested','approved','rejected','received','refunded'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS return_items (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_request_id BIGINT UNSIGNED NOT NULL,
  order_item_id     BIGINT UNSIGNED NOT NULL,
  batch_id          BIGINT UNSIGNED NULL,                -- lo nhap lai kho neu restock = 1
  qty               INT UNSIGNED    NOT NULL,
  restock           TINYINT(1)      NOT NULL DEFAULT 0,
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_return_items_request_item (return_request_id, order_item_id),
  KEY idx_return_items_order_item (order_item_id),
  KEY idx_return_items_batch (batch_id),
  CONSTRAINT fk_ri_request    FOREIGN KEY (return_request_id) REFERENCES return_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_order_item FOREIGN KEY (order_item_id)     REFERENCES order_items (id),
  CONSTRAINT fk_ri_batch      FOREIGN KEY (batch_id)          REFERENCES batches (id),
  CONSTRAINT ck_ri_qty        CHECK (qty >= 1),
  CONSTRAINT ck_ri_restock    CHECK (restock = 0 OR batch_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  type       VARCHAR(60)     NOT NULL,
  data       JSON            NOT NULL,
  read_at    TIMESTAMP       NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_unread (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
