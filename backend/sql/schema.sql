-- Схема базы мастерской «Handmade» для MySQL 5.7+ / MariaDB 10.3+.
-- Импорт: phpMyAdmin → Импорт, или php backend/bin/install.php.
-- Время хранится в UTC. Деньги — в копейках. JSON-поля — текстом.

SET NAMES utf8mb4;

-- ─── Core ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL,
  passwordHash VARCHAR(255) NOT NULL,
  name VARCHAR(191) NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY admins_email_key (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Произвольные настройки (key → JSON). Ключ "site" — публичная карточка заведения.
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  value LONGTEXT NOT NULL,
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Страница лендинга: набор блоков (hero, features, gallery, faq…) в JSON.
CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  seoTitle VARCHAR(255) NULL,
  seoDescription TEXT NULL,
  blocks LONGTEXT NOT NULL,
  isPublished TINYINT(1) NOT NULL DEFAULT 1,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY pages_slug_key (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заявки с форм: type contact | callback | event | custom, status new | in_progress | done | spam
CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(20) NOT NULL DEFAULT 'contact',
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(32) NULL,
  email VARCHAR(255) NULL,
  message TEXT NULL,
  meta TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY leads_status_createdAt_idx (status, createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Меню (кафе, без корзины) ────────────────────────────────────────

CREATE TABLE IF NOT EXISTS menu_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description TEXT NULL,
  sort INT NOT NULL DEFAULT 0,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY menu_categories_slug_key (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoryId INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  price INT NOT NULL,          -- копейки
  oldPrice INT NULL,
  weight VARCHAR(100) NULL,    -- "250 г", "0.5 л"
  imageUrl VARCHAR(500) NULL,
  tags TEXT NOT NULL,          -- JSON: ["veg","spicy","new"]
  isAvailable TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY menu_items_categoryId_sort_idx (categoryId, sort),
  CONSTRAINT menu_items_categoryId_fkey FOREIGN KEY (categoryId) REFERENCES menu_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Магазин (каталог и заказы) ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS shop_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  title VARCHAR(255) NULL,        -- заголовок страницы каталога
  subtitle VARCHAR(500) NULL,     -- подзаголовок страницы каталога
  shortName VARCHAR(100) NULL,    -- короткое имя для фильтров и хлебных крошек
  sort INT NOT NULL DEFAULT 0,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY shop_categories_slug_key (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoryId INT UNSIGNED NOT NULL,
  slug VARCHAR(100) NOT NULL,     -- адрес товара: product.html?id=<slug>
  name VARCHAR(255) NOT NULL,
  price INT NOT NULL,             -- копейки
  oldPrice INT NULL,
  badge VARCHAR(50) NULL,         -- «Хит», «Новинка», «Выгодно»
  shortDescription VARCHAR(500) NULL,
  description TEXT NULL,
  specs TEXT NOT NULL,            -- JSON: [{"name":"Материалы","value":"бисер"}]
  imageUrl VARCHAR(500) NULL,
  tone VARCHAR(20) NOT NULL DEFAULT '', -- тонировка фото: photo | neutral | ''
  zoom DECIMAL(4,2) NULL,         -- увеличение фото, например 1.6
  isFeatured TINYINT(1) NOT NULL DEFAULT 0, -- показывать на главной
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY shop_products_slug_key (slug),
  KEY shop_products_categoryId_sort_idx (categoryId, sort),
  CONSTRAINT shop_products_categoryId_fkey FOREIGN KEY (categoryId) REFERENCES shop_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: new | confirmed | shipped | done | cancelled | spam
CREATE TABLE IF NOT EXISTS shop_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(16) NOT NULL,      -- номер заказа для клиента: HM-8F3K2
  customerName VARCHAR(255) NOT NULL,
  customerPhone VARCHAR(32) NOT NULL,
  customerEmail VARCHAR(255) NULL,
  deliveryMethod VARCHAR(20) NOT NULL DEFAULT 'delivery', -- delivery | pickup
  city VARCHAR(191) NULL,
  address VARCHAR(500) NULL,
  comment TEXT NULL,
  payment VARCHAR(20) NOT NULL DEFAULT 'card', -- card | transfer | cash
  subtotal INT NOT NULL,          -- копейки
  deliveryPrice INT NOT NULL DEFAULT 0,
  total INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  adminNote TEXT NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY shop_orders_code_key (code),
  KEY shop_orders_status_createdAt_idx (status, createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Позиции заказа: название и цена копируются, чтобы история не менялась при правке товара.
CREATE TABLE IF NOT EXISTS shop_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orderId INT UNSIGNED NOT NULL,
  productId INT UNSIGNED NULL,
  slug VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  price INT NOT NULL,             -- копейки за штуку
  qty INT NOT NULL,
  total INT NOT NULL,
  imageUrl VARCHAR(500) NULL,
  KEY shop_order_items_orderId_idx (orderId),
  CONSTRAINT shop_order_items_orderId_fkey FOREIGN KEY (orderId) REFERENCES shop_orders (id) ON DELETE CASCADE,
  CONSTRAINT shop_order_items_productId_fkey FOREIGN KEY (productId) REFERENCES shop_products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Бронирование ────────────────────────────────────────────────────

-- Бронируемый ресурс: стол, комната, дорожка. capacity — сколько броней одновременно.
CREATE TABLE IF NOT EXISTS resources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  type VARCHAR(50) NULL,
  description TEXT NULL,
  imageUrl VARCHAR(500) NULL,
  capacity INT NOT NULL DEFAULT 1,
  maxGuests INT NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  UNIQUE KEY resources_slug_key (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Услуга: «Бронь стола», «Квест 60 мин». slotStepMin NULL → BOOKING_SLOT_STEP_MIN.
CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description TEXT NULL,
  imageUrl VARCHAR(500) NULL,
  durationMin INT NOT NULL,
  bufferMin INT NOT NULL DEFAULT 0,
  slotStepMin INT NULL,
  price INT NOT NULL DEFAULT 0,
  pricePerGuest TINYINT(1) NOT NULL DEFAULT 0,
  minGuests INT NOT NULL DEFAULT 1,
  maxGuests INT NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  UNIQUE KEY services_slug_key (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_service (
  resourceId INT UNSIGNED NOT NULL,
  serviceId INT UNSIGNED NOT NULL,
  PRIMARY KEY (resourceId, serviceId),
  KEY resource_service_serviceId_idx (serviceId),
  CONSTRAINT resource_service_resourceId_fkey FOREIGN KEY (resourceId) REFERENCES resources (id) ON DELETE CASCADE,
  CONSTRAINT resource_service_serviceId_fkey FOREIGN KEY (serviceId) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Часы работы. resourceId NULL — общий график. weekday: 1 = пн … 7 = вс.
-- closeTime <= openTime — работа после полуночи.
CREATE TABLE IF NOT EXISTS working_hours (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resourceId INT UNSIGNED NULL,
  weekday TINYINT NOT NULL,
  openTime CHAR(5) NOT NULL,
  closeTime CHAR(5) NOT NULL,
  KEY working_hours_resourceId_weekday_idx (resourceId, weekday),
  CONSTRAINT working_hours_resourceId_fkey FOREIGN KEY (resourceId) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Исключения: праздники, санитарные дни, сокращённые часы. date — "2026-12-31" по времени заведения.
CREATE TABLE IF NOT EXISTS schedule_exceptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resourceId INT UNSIGNED NULL,
  date CHAR(10) NOT NULL,
  isClosed TINYINT(1) NOT NULL DEFAULT 1,
  openTime CHAR(5) NULL,
  closeTime CHAR(5) NULL,
  note VARCHAR(255) NULL,
  KEY schedule_exceptions_date_idx (date),
  CONSTRAINT schedule_exceptions_resourceId_fkey FOREIGN KEY (resourceId) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: pending | confirmed | cancelled | completed | no_show
CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(16) NOT NULL,
  resourceId INT UNSIGNED NOT NULL,
  serviceId INT UNSIGNED NOT NULL,
  startAt DATETIME(3) NOT NULL,
  endAt DATETIME(3) NOT NULL,
  guests INT NOT NULL DEFAULT 1,
  customerName VARCHAR(255) NOT NULL,
  customerPhone VARCHAR(32) NOT NULL,
  customerEmail VARCHAR(255) NULL,
  comment TEXT NULL,
  totalPrice INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  adminNote TEXT NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY bookings_code_key (code),
  KEY bookings_resourceId_startAt_idx (resourceId, startAt),
  KEY bookings_status_startAt_idx (status, startAt),
  CONSTRAINT bookings_resourceId_fkey FOREIGN KEY (resourceId) REFERENCES resources (id),
  CONSTRAINT bookings_serviceId_fkey FOREIGN KEY (serviceId) REFERENCES services (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Счётчики анти-спама (10 форм / 15 мин с IP)
CREATE TABLE IF NOT EXISTS rate_limits (
  `key` CHAR(64) NOT NULL PRIMARY KEY,
  hits INT NOT NULL,
  resetAt DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
