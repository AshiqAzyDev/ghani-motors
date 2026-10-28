-- Ghani Motors MySQL 8 schema
-- InnoDB, utf8mb4. Money is signed integer pence. Autotrader bodies are JSON.
--
-- One Cloudtechnologies integration, many dealers. Ghani Motors is the first.
-- Credentials stay in the environment. This database stores tokens, not the key or secret.
--
-- Access tokens expire after 15 minutes. Reuse the current token until shortly
-- before expires_at. Do not call /authenticate before every API request.
-- A scheduled call at least every few weeks updates integrations.credentials_last_used_at
-- so Autotrader does not remove an idle key after 12 weeks.
--
-- Notification endpoint:
--   valid hash   -> store the event and return 2xx
--   invalid hash -> store nothing useful, return 401
-- Discard (status ignored, still 2xx) when change_fields is present and contains
-- none of the fields this integration stores.
-- Apply only when notification_time is newer than vehicles.last_notification_at,
-- or the timestamp is equal and the payload hash differs.
-- A duplicate (advertiser, stock, time, hash) returns 2xx and does not write twice.
-- If webhooks fail for an extended period, set notifications_paused, run a baseline,
-- then resume. A full baseline replaces images, features, highlights, and prices.
-- A partial webhook must not wipe sections that were not in that change.
-- After a baseline finishes with no error, cars for that dealer missing from the
-- result set are hidden, not deleted.
--
-- Public site shows a car only when show_on_website = 1.
-- The application sets that flag only for Autotrader cars whose website_status
-- is available or reserved, advertiser_advert_status is PUBLISHED, and no local
-- completed sale exists. Any other advert status is hidden. Manual cars stay hidden.
-- A completed local sale stays sold even if a later webhook says FORECOURT.
-- An Autotrader reservation can reserve a car with no local booking. Clearing it
-- must not cancel a local held reservation.
-- Public cash price follows total_price_pence when Autotrader sends it.
-- Do not read vehicle.previousOwners. Use vehicle.owners. Previous owners, if
-- needed, come from history.previousOwners.
--
-- Error handling, recorded in api_call_log (always store CF-RAY on failure)
-- and api_blocks:
--   429 pause every service, then retry
--   503 pause only that service for at least two seconds
--   400 retryable = 0, needs_action = 1, no scheduled retry
--   401 global pause until a new api_access_tokens row exists
--   403 advertiser not on our list: block all services for that advertiser
--   403 missing product: block only that service for that advertiser
--   Stock API 403: do not retry that advertiser
--
-- Stock-features and charge-times are called once per derivative, never on a
-- schedule. An empty charge-time response still writes vehicle_charge_time_fetches.
-- Repeat features only when derivative_id or the feature list changes.
-- Repeat charge times only when derivative_id changes, and only for electric
-- or hybrid fuel. Sandbox and production stock must not mix (environment).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS ghani_motors
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ghani_motors;

-- ---------------------------------------------------------------------------
-- Integration and dealers
-- ---------------------------------------------------------------------------

CREATE TABLE integrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  at_integration_id VARCHAR(64) NULL COMMENT 'From the Integrations API. Ignore STOCK_UPDATE whose integrationId equals this if stock write-back is ever enabled.',
  environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
  credentials_last_used_at DATETIME NULL,
  notifications_paused TINYINT(1) NOT NULL DEFAULT 0,
  pause_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY integrations_at_integration_id_unique (at_integration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dealers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  trading_name VARCHAR(160) NULL,
  tagline VARCHAR(160) NULL,
  advertiser_id VARCHAR(32) NULL COMMENT 'Autotrader advertiserId. Public URL suggests 10042292 until confirmed.',
  phone VARCHAR(40) NULL,
  email VARCHAR(255) NULL,
  address_line VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  postcode VARCHAR(16) NULL,
  website_host VARCHAR(255) NULL COMMENT 'Nullable so a later dealer can have its own site.',
  logo_path VARCHAR(255) NULL,
  favicon_path VARCHAR(255) NULL,
  opening_hours VARCHAR(255) NULL,
  on_integration TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Advertiser webhooks do not flip this. A scheduled Advertisers API pull does.',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY dealers_advertiser_id_unique (advertiser_id),
  KEY dealers_integration_id_index (integration_id),
  CONSTRAINT dealers_integration_id_foreign
    FOREIGN KEY (integration_id) REFERENCES integrations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dealer_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'sales', 'viewer') NOT NULL DEFAULT 'sales',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY staff_users_email_unique (email),
  KEY staff_users_dealer_id_index (dealer_id),
  CONSTRAINT staff_users_dealer_id_foreign
    FOREIGN KEY (dealer_id) REFERENCES dealers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tokens, call log, blocks
-- ---------------------------------------------------------------------------

CREATE TABLE api_access_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_id BIGINT UNSIGNED NOT NULL,
  access_token TEXT NOT NULL,
  obtained_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL COMMENT 'Autotrader tokens last 15 minutes.',
  invalidated_at DATETIME NULL COMMENT 'Set on 401. No API calls until a newer token exists.',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY api_access_tokens_current_index (integration_id, invalidated_at, expires_at),
  CONSTRAINT api_access_tokens_integration_id_foreign
    FOREIGN KEY (integration_id) REFERENCES integrations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_call_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_id BIGINT UNSIGNED NOT NULL,
  service ENUM('auth', 'advertisers', 'stock', 'stock_features', 'stock_charge_times') NOT NULL,
  advertiser_id VARCHAR(32) NULL,
  stock_id VARCHAR(64) NULL,
  http_status SMALLINT UNSIGNED NULL,
  cf_ray_id VARCHAR(64) NULL COMMENT 'Response header CF-RAY. Required on failures.',
  path VARCHAR(500) NOT NULL,
  error_body TEXT NULL,
  retryable TINYINT(1) NOT NULL DEFAULT 0,
  needs_action TINYINT(1) NOT NULL DEFAULT 0,
  retry_after_seconds INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY api_call_log_integration_created_index (integration_id, created_at),
  KEY api_call_log_cf_ray_index (cf_ray_id),
  CONSTRAINT api_call_log_integration_id_foreign
    FOREIGN KEY (integration_id) REFERENCES integrations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_id BIGINT UNSIGNED NOT NULL,
  scope ENUM('global', 'service', 'advertiser_service') NOT NULL,
  service ENUM('auth', 'advertisers', 'stock', 'stock_features', 'stock_charge_times') NULL,
  advertiser_id VARCHAR(32) NULL,
  pause_until DATETIME NULL,
  reason VARCHAR(255) NOT NULL,
  cf_ray_id VARCHAR(64) NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY api_blocks_open_index (integration_id, resolved_at, scope, advertiser_id, service),
  CONSTRAINT api_blocks_integration_id_foreign
    FOREIGN KEY (integration_id) REFERENCES integrations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Customers before vehicles so later FKs are simple. Vehicles do not require them.
-- ---------------------------------------------------------------------------

CREATE TABLE customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dealer_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  password_hash VARCHAR(255) NULL COMMENT 'Null until the customer registers.',
  address VARCHAR(255) NULL,
  postcode VARCHAR(16) NULL,
  notes TEXT NULL,
  source ENUM('showroom', 'phone', 'website_enquiry', 'finance_enquiry', 'sell_your_car', 'other') NOT NULL DEFAULT 'website_enquiry',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY customers_email_unique (email),
  KEY customers_dealer_id_index (dealer_id),
  CONSTRAINT customers_dealer_id_foreign
    FOREIGN KEY (dealer_id) REFERENCES dealers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Stock
-- ---------------------------------------------------------------------------

CREATE TABLE vehicles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dealer_id BIGINT UNSIGNED NOT NULL,
  environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
  source ENUM('autotrader', 'manual') NOT NULL DEFAULT 'autotrader',
  show_on_website TINYINT(1) NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  slug VARCHAR(180) NULL,
  at_stock_id VARCHAR(64) NULL,
  at_search_id VARCHAR(64) NULL,
  external_stock_id VARCHAR(64) NULL,
  external_stock_reference VARCHAR(64) NULL,
  registration VARCHAR(16) NULL,
  vin VARCHAR(32) NULL,
  derivative_id VARCHAR(64) NULL,
  make VARCHAR(80) NULL,
  model VARCHAR(80) NULL,
  trim VARCHAR(160) NULL,
  year SMALLINT UNSIGNED NULL,
  mileage INT UNSIGNED NULL,
  fuel VARCHAR(80) NULL COMMENT 'Free text. Autotrader values do not fit a short enum.',
  transmission VARCHAR(80) NULL,
  body VARCHAR(80) NULL,
  colour VARCHAR(80) NULL,
  doors SMALLINT UNSIGNED NULL,
  seats SMALLINT UNSIGNED NULL,
  engine_cc INT UNSIGNED NULL,
  co2_emissions INT UNSIGNED NULL,
  owners SMALLINT UNSIGNED NULL COMMENT 'vehicle.owners, including the current owner.',
  history_previous_owners SMALLINT UNSIGNED NULL COMMENT 'history.previousOwners. Never vehicle.previousOwners.',
  mot_expiry DATE NULL,
  first_registration_on DATE NULL,
  cash_price_pence INT NULL,
  supplied_price_pence INT NULL,
  admin_fee_pence INT NULL,
  total_price_pence INT NULL,
  vat_status VARCHAR(40) NULL,
  price_indicator_rating VARCHAR(40) NULL,
  attention_grabber VARCHAR(255) NULL,
  description TEXT NULL,
  description_2 TEXT NULL,
  video_url VARCHAR(500) NULL,
  spin_url VARCHAR(500) NULL,
  advertiser_advert_status VARCHAR(40) NULL COMMENT 'PUBLISHED may be shown. Anything else is hidden.',
  retail_adverts JSON NULL COMMENT 'Other retailAdverts destination statuses.',
  at_lifecycle VARCHAR(40) NULL,
  reservation_status VARCHAR(40) NULL COMMENT 'Autotrader reservation. Independent of local bookings.',
  rarity_rating VARCHAR(40) NULL,
  value_rating VARCHAR(40) NULL,
  at_updated_at DATETIME NULL,
  last_notification_at DATETIME NULL,
  purchase_cost_pence INT NOT NULL DEFAULT 0,
  prep_cost_pence INT NOT NULL DEFAULT 0,
  internal_notes TEXT NULL,
  local_hold ENUM('none', 'preparation', 'reserved') NOT NULL DEFAULT 'none',
  website_status ENUM('available', 'reserved', 'preparation', 'sold', 'hidden') NOT NULL DEFAULT 'hidden',
  missing_from_baseline_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY vehicles_at_stock_id_unique (at_stock_id),
  UNIQUE KEY vehicles_slug_unique (slug),
  KEY vehicles_public_index (dealer_id, show_on_website, website_status),
  KEY vehicles_derivative_index (derivative_id),
  KEY vehicles_environment_index (dealer_id, environment),
  CONSTRAINT vehicles_dealer_id_foreign
    FOREIGN KEY (dealer_id) REFERENCES dealers (id),
  CONSTRAINT vehicles_created_by_foreign
    FOREIGN KEY (created_by) REFERENCES staff_users (id),
  CONSTRAINT vehicles_updated_by_foreign
    FOREIGN KEY (updated_by) REFERENCES staff_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sync_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_id BIGINT UNSIGNED NOT NULL,
  dealer_id BIGINT UNSIGNED NOT NULL,
  environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
  kind ENUM('baseline', 'replay', 'advertisers') NOT NULL,
  status ENUM('pending', 'running', 'succeeded', 'failed') NOT NULL DEFAULT 'pending',
  page_cursor VARCHAR(255) NULL,
  total_results INT UNSIGNED NULL,
  pages_done INT UNSIGNED NOT NULL DEFAULT 0,
  vehicles_upserted INT UNSIGNED NOT NULL DEFAULT 0,
  error TEXT NULL,
  started_by BIGINT UNSIGNED NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY sync_runs_dealer_status_index (dealer_id, status),
  CONSTRAINT sync_runs_integration_id_foreign
    FOREIGN KEY (integration_id) REFERENCES integrations (id),
  CONSTRAINT sync_runs_dealer_id_foreign
    FOREIGN KEY (dealer_id) REFERENCES dealers (id),
  CONSTRAINT sync_runs_started_by_foreign
    FOREIGN KEY (started_by) REFERENCES staff_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_id BIGINT UNSIGNED NOT NULL,
  dealer_id BIGINT UNSIGNED NULL,
  vehicle_id BIGINT UNSIGNED NULL,
  advertiser_id VARCHAR(32) NULL,
  stock_id VARCHAR(64) NULL,
  event_type VARCHAR(40) NOT NULL COMMENT 'STOCK_UPDATE or ADVERTISER',
  notification_time DATETIME NULL,
  source_integration_id VARCHAR(64) NULL COMMENT 'integrationId on the payload. Compare with integrations.at_integration_id.',
  change_fields JSON NULL,
  payload_hash CHAR(64) NOT NULL,
  raw_body JSON NOT NULL,
  signature_valid TINYINT(1) NOT NULL,
  status ENUM('received', 'applied', 'ignored', 'failed') NOT NULL DEFAULT 'received',
  ignore_reason VARCHAR(255) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY webhook_events_dedupe_unique (advertiser_id, stock_id, notification_time, payload_hash),
  KEY webhook_events_vehicle_index (vehicle_id),
  KEY webhook_events_status_index (status, received_at),
  CONSTRAINT webhook_events_integration_id_foreign
    FOREIGN KEY (integration_id) REFERENCES integrations (id),
  CONSTRAINT webhook_events_dealer_id_foreign
    FOREIGN KEY (dealer_id) REFERENCES dealers (id),
  CONSTRAINT webhook_events_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_payloads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  sync_run_id BIGINT UNSIGNED NULL,
  webhook_event_id BIGINT UNSIGNED NULL,
  body JSON NOT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY vehicle_payloads_vehicle_index (vehicle_id, fetched_at),
  CONSTRAINT vehicle_payloads_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
  CONSTRAINT vehicle_payloads_sync_run_id_foreign
    FOREIGN KEY (sync_run_id) REFERENCES sync_runs (id),
  CONSTRAINT vehicle_payloads_webhook_event_id_foreign
    FOREIGN KEY (webhook_event_id) REFERENCES webhook_events (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY vehicle_images_order_unique (vehicle_id, sort_order),
  CONSTRAINT vehicle_images_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_features (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('standard', 'optional') NOT NULL,
  name VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY vehicle_features_vehicle_index (vehicle_id, kind),
  CONSTRAINT vehicle_features_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_highlights (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY vehicle_highlights_vehicle_index (vehicle_id, sort_order),
  CONSTRAINT vehicle_highlights_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_price_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  cash_price_pence INT NOT NULL,
  source ENUM('sync', 'webhook', 'manual') NOT NULL,
  sync_run_id BIGINT UNSIGNED NULL,
  webhook_event_id BIGINT UNSIGNED NULL,
  changed_by BIGINT UNSIGNED NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY vehicle_price_history_vehicle_index (vehicle_id, recorded_at),
  CONSTRAINT vehicle_price_history_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
  CONSTRAINT vehicle_price_history_sync_run_id_foreign
    FOREIGN KEY (sync_run_id) REFERENCES sync_runs (id),
  CONSTRAINT vehicle_price_history_webhook_event_id_foreign
    FOREIGN KEY (webhook_event_id) REFERENCES webhook_events (id),
  CONSTRAINT vehicle_price_history_changed_by_foreign
    FOREIGN KEY (changed_by) REFERENCES staff_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_taxonomy_fetches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  derivative_id VARCHAR(64) NULL,
  features_fingerprint CHAR(64) NULL COMMENT 'Repeat the stock-features call only when derivative_id or this fingerprint changes.',
  fetched_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY vehicle_taxonomy_fetches_vehicle_unique (vehicle_id),
  CONSTRAINT vehicle_taxonomy_fetches_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_charge_time_fetches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  derivative_id VARCHAR(64) NULL,
  result_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Zero is a valid stored result. Do not call again until derivative_id changes.',
  fetched_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY vehicle_charge_time_fetches_vehicle_unique (vehicle_id),
  CONSTRAINT vehicle_charge_time_fetches_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_charge_times (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  derivative_id VARCHAR(64) NULL,
  charger_type VARCHAR(80) NOT NULL,
  duration_minutes INT UNSIGNED NULL,
  fetched_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY vehicle_charge_times_vehicle_index (vehicle_id),
  CONSTRAINT vehicle_charge_times_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Enquiries, bookings, saved cars
-- ---------------------------------------------------------------------------

CREATE TABLE saved_vehicles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY saved_vehicles_unique (customer_id, vehicle_id),
  CONSTRAINT saved_vehicles_customer_id_foreign
    FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT saved_vehicles_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  vehicle_id BIGINT UNSIGNED NULL,
  assigned_to BIGINT UNSIGNED NULL,
  type ENUM('finance', 'viewing', 'general', 'sell', 'part_exchange') NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NULL,
  status ENUM('new', 'contacted', 'closed') NOT NULL DEFAULT 'new',
  accepted_terms_at DATETIME NULL,
  created_on DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY leads_customer_index (customer_id),
  KEY leads_vehicle_index (vehicle_id),
  CONSTRAINT leads_customer_id_foreign
    FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT leads_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
  CONSTRAINT leads_assigned_to_foreign
    FOREIGN KEY (assigned_to) REFERENCES staff_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE valuations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NOT NULL,
  registration VARCHAR(16) NOT NULL,
  mileage INT UNSIGNED NOT NULL,
  make VARCHAR(80) NOT NULL,
  model VARCHAR(80) NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  fuel VARCHAR(80) NOT NULL,
  transmission VARCHAR(80) NOT NULL,
  intent ENUM('sell', 'part_exchange') NOT NULL,
  `condition` ENUM('excellent', 'good', 'fair', 'needs_attention') NOT NULL,
  service_history ENUM('full', 'partial', 'none', 'not_sure') NOT NULL,
  mot_expiry DATE NULL,
  outstanding_finance ENUM('yes', 'no', 'not_sure') NOT NULL,
  notes TEXT NULL,
  preferred_contact ENUM('phone', 'email') NOT NULL,
  accepted_terms_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY valuations_lead_id_unique (lead_id),
  CONSTRAINT valuations_lead_id_foreign
    FOREIGN KEY (lead_id) REFERENCES leads (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE valuation_photos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  valuation_id BIGINT UNSIGNED NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY valuation_photos_valuation_index (valuation_id, sort_order),
  CONSTRAINT valuation_photos_valuation_id_foreign
    FOREIGN KEY (valuation_id) REFERENCES valuations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  type ENUM('reservation', 'test_drive', 'viewing') NOT NULL,
  status ENUM('held', 'requested', 'confirmed', 'completed', 'cancelled') NOT NULL,
  preferred_on DATE NOT NULL,
  preferred_time TIME NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  held_reservation_key BIGINT UNSIGNED
    AS (IF(type = 'reservation' AND status = 'held', vehicle_id, NULL)) STORED,
  active_test_drive_key VARCHAR(80)
    AS (IF(type IN ('test_drive', 'viewing') AND status NOT IN ('cancelled', 'completed'),
           CONCAT(vehicle_id, '|', preferred_on, '|', IFNULL(TIME_FORMAT(preferred_time, '%H:%i'), '')),
           NULL)) STORED,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY bookings_one_held_reservation (held_reservation_key),
  UNIQUE KEY bookings_one_active_test_drive (active_test_drive_key),
  KEY bookings_vehicle_index (vehicle_id),
  KEY bookings_customer_index (customer_id),
  CONSTRAINT bookings_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
  CONSTRAINT bookings_customer_id_foreign
    FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT bookings_created_by_foreign
    FOREIGN KEY (created_by) REFERENCES staff_users (id),
  CONSTRAINT bookings_updated_by_foreign
    FOREIGN KEY (updated_by) REFERENCES staff_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Sales and invoices
-- ---------------------------------------------------------------------------

CREATE TABLE invoice_counters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dealer_id BIGINT UNSIGNED NOT NULL,
  next_number INT UNSIGNED NOT NULL DEFAULT 1001,
  PRIMARY KEY (id),
  UNIQUE KEY invoice_counters_dealer_unique (dealer_id),
  CONSTRAINT invoice_counters_dealer_id_foreign
    FOREIGN KEY (dealer_id) REFERENCES dealers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  part_exchange_valuation_id BIGINT UNSIGNED NULL,
  vehicle_label VARCHAR(180) NOT NULL,
  registration VARCHAR(16) NULL,
  price_pence INT NOT NULL,
  sold_on DATE NOT NULL,
  method ENUM('bank_transfer', 'card', 'finance', 'cash') NOT NULL,
  status ENUM('completed', 'cancelled') NOT NULL DEFAULT 'completed',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  completed_vehicle_key BIGINT UNSIGNED
    AS (IF(status = 'completed', vehicle_id, NULL)) STORED,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY sales_one_completed_per_vehicle (completed_vehicle_key),
  UNIQUE KEY sales_part_exchange_unique (part_exchange_valuation_id),
  KEY sales_customer_index (customer_id),
  CONSTRAINT sales_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
  CONSTRAINT sales_customer_id_foreign
    FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT sales_part_exchange_foreign
    FOREIGN KEY (part_exchange_valuation_id) REFERENCES valuations (id),
  CONSTRAINT sales_created_by_foreign
    FOREIGN KEY (created_by) REFERENCES staff_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id BIGINT UNSIGNED NULL,
  number VARCHAR(20) NOT NULL,
  kind ENUM('invoice', 'credit_note') NOT NULL DEFAULT 'invoice',
  related_invoice_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  customer_snapshot JSON NOT NULL,
  vehicle_snapshot JSON NOT NULL,
  vat_scheme ENUM('standard', 'margin', 'exempt', 'zero_rated') NOT NULL,
  prices_include_vat TINYINT(1) NOT NULL DEFAULT 1,
  net_pence INT NOT NULL DEFAULT 0,
  vat_pence INT NOT NULL DEFAULT 0,
  total_pence INT NOT NULL DEFAULT 0,
  vat_rate_bps INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '2000 means 20.00 percent.',
  status ENUM('draft', 'issued', 'part_paid', 'paid', 'void', 'credited') NOT NULL DEFAULT 'draft',
  issued_at DATETIME NULL,
  voided_at DATETIME NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY invoices_number_unique (number),
  UNIQUE KEY invoices_sale_id_unique (sale_id),
  KEY invoices_customer_index (customer_id),
  CONSTRAINT invoices_sale_id_foreign
    FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT invoices_related_invoice_id_foreign
    FOREIGN KEY (related_invoice_id) REFERENCES invoices (id),
  CONSTRAINT invoices_customer_id_foreign
    FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT invoices_created_by_foreign
    FOREIGN KEY (created_by) REFERENCES staff_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  line_type ENUM('vehicle', 'extra', 'part_exchange', 'fee', 'discount') NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
  unit_net_pence INT NOT NULL,
  vat_rate_bps INT UNSIGNED NOT NULL DEFAULT 0,
  vat_pence INT NOT NULL DEFAULT 0,
  line_total_pence INT NOT NULL,
  PRIMARY KEY (id),
  KEY invoice_lines_invoice_index (invoice_id, sort_order),
  CONSTRAINT invoice_lines_invoice_id_foreign
    FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  amount_pence INT NOT NULL,
  received_on DATE NOT NULL,
  method ENUM('bank_transfer', 'card', 'finance', 'cash') NOT NULL,
  reference VARCHAR(100) NOT NULL,
  recorded_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY invoice_payments_invoice_index (invoice_id),
  CONSTRAINT invoice_payments_invoice_id_foreign
    FOREIGN KEY (invoice_id) REFERENCES invoices (id),
  CONSTRAINT invoice_payments_recorded_by_foreign
    FOREIGN KEY (recorded_by) REFERENCES staff_users (id),
  CONSTRAINT invoice_payments_amount_positive CHECK (amount_pence > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_user_id BIGINT UNSIGNED NULL,
  vehicle_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  message VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY activity_log_created_index (created_at),
  CONSTRAINT activity_log_staff_user_id_foreign
    FOREIGN KEY (staff_user_id) REFERENCES staff_users (id),
  CONSTRAINT activity_log_vehicle_id_foreign
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
  CONSTRAINT activity_log_customer_id_foreign
    FOREIGN KEY (customer_id) REFERENCES customers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
