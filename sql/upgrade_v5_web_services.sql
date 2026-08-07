-- Public service catalogue + gallery (run once on existing databases)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS web_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  short_description VARCHAR(512) NOT NULL,
  description TEXT NOT NULL,
  what_we_do TEXT NOT NULL,
  features_json TEXT,
  benefits_text TEXT,
  price_from DECIMAL(10,2) DEFAULT NULL,
  price_note VARCHAR(255) DEFAULT NULL,
  cover_image VARCHAR(512) DEFAULT NULL,
  lucide_icon VARCHAR(64) NOT NULL DEFAULT 'wrench',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_web_services_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_service_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_web_svc_img_service_v5 FOREIGN KEY (service_id) REFERENCES web_services(id) ON DELETE CASCADE,
  INDEX idx_web_svc_img_sort (service_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO web_services (id, slug, name, short_description, description, what_we_do, features_json, benefits_text, price_from, price_note, cover_image, lucide_icon, sort_order, active) VALUES
(1, 'computer', 'Computer repair', 'Laptops, desktops, OS, upgrades, virus cleanup.', 'Professional computer repair for homes and businesses. We fix hardware faults, slow systems, and software issues with clear turnaround times.', 'We diagnose power and board issues, replace failing storage and memory, reinstall or tune operating systems, remove malware, and recover data when possible. Workshop or on-site service depending on the job.', '[{"icon":"laptop","text":"Laptop screen, keyboard, battery & charging repairs"},{"icon":"cpu","text":"Desktop upgrades, cleaning, thermal & stability testing"},{"icon":"shield-check","text":"Virus removal, security basics & backup guidance"}]', 'Transparent estimates before major parts\nWarranty-friendly documentation\nParts sourcing for upgrades', 2500.00, 'Diagnostic visit from LKR 2,500 — final price after assessment', 'assets/images/services/svc-computer.svg', 'laptop', 1, 1),
(2, 'printer', 'Printer service', 'Cartridges, jams, rollers, refills, office printers.', 'Keep your office printing reliably. We service inkjet and laser printers, fix jams, replace worn rollers, and handle refills.', 'We clean paper paths, replace pickup rollers, service cartridges and toners, align print quality, and advise when a printer is beyond economical repair.', '[{"icon":"printer","text":"Laser & inkjet service"},{"icon":"refresh-cw","text":"Cartridge refill & toner support"},{"icon":"wrench","text":"Jam recovery, rollers & mechanical fixes"}]', 'Test prints shown before you pay\nSpare parts where available\nAMC options for busy offices', 1500.00, 'Basic service from LKR 1,500 — parts quoted separately', 'assets/images/services/svc-printer.svg', 'printer', 2, 1),
(3, 'cctv', 'CCTV installation', 'Cameras, DVR/NVR, cabling, remote viewing setup.', 'Design and install CCTV systems that are easy to use and maintain. Ideal for shops, offices, warehouses, and homes.', 'We survey camera positions, run structured cabling where needed, mount cameras, configure DVR/NVR recording, and set up phone or PC viewing.', '[{"icon":"video","text":"HD / IP camera installation"},{"icon":"cable","text":"Cabling, power & neat trunking"},{"icon":"smartphone","text":"Remote viewing on mobile & PC"}]', 'Neat cable routing\nTraining on playback & export\nMaintenance visits available', 8500.00, 'Site survey & starter packages from LKR 8,500', 'assets/images/services/svc-cctv.svg', 'video', 3, 1),
(4, 'maintenance', 'Maintenance', 'AMC, scheduled visits, health checks.', 'Planned maintenance reduces downtime. We offer scheduled visits for IT gear, printers, CCTV, and mixed environments.', 'We agree a visit schedule, run health checks, clean and test equipment, update logs, and flag issues before they become outages.', '[{"icon":"calendar-check","text":"Scheduled AMC visits"},{"icon":"clipboard-list","text":"Checklists & service reports"},{"icon":"headphones","text":"Priority support for contract customers"}]', 'Predictable yearly costs\nFewer emergency call-outs\nPriority booking during peaks', 4000.00, 'AMC plans from LKR 4,000 / year (varies by scope)', 'assets/images/services/svc-maintenance.svg', 'wrench', 4, 1),
(5, 'automobile', 'Automobile breakdown', 'Roadside-style support with emergency priority.', 'When your vehicle lets you down, we prioritise breakdown requests and aim to get you moving or towed safely.', 'We attend common roadside faults where possible, coordinate battery and minor on-site fixes, and escalate to workshop partners for major repairs.', '[{"icon":"car-front","text":"Breakdown & roadside assistance"},{"icon":"zap","text":"Battery & electrical checks"},{"icon":"alert-circle","text":"Emergency flag for fastest dispatch"}]', 'Emergency option for urgent cases\nClear communication on ETA\nLinks to trusted garages when needed', 3500.00, 'Call-out from LKR 3,500 — labour & parts extra', 'assets/images/services/svc-automobile.svg', 'car-front', 5, 1),
(6, 'ac', 'AC repair', 'Split & window units, gas, cleaning, faults.', 'Comfortable cooling with proper gas handling and cleaning. We service split and window air conditioners.', 'We deep-clean filters and coils, check drainage, test compressor and fan health, and handle safe refrigerant top-ups where appropriate.', '[{"icon":"wind","text":"Gas refill & leak checks (where applicable)"},{"icon":"droplets","text":"Drain & mould prevention cleaning"},{"icon":"thermometer","text":"Cooling performance testing"}]', 'Seasonal service reminders\nHonest advice on replace vs repair\nSafer handling of refrigerants', 3000.00, 'General service from LKR 3,000', 'assets/images/services/svc-ac.svg', 'snowflake', 6, 1),
(7, 'electrical', 'Electrical (DC)', 'DC wiring, solar/aux circuits, safe installs.', 'Low-voltage DC wiring for solar auxiliaries, CCTV power runs, and safe installs that follow best practice.', 'We plan cable sizes and protection, terminate professionally, label circuits, and test load conditions for CCTV, lighting, and auxiliary DC systems.', '[{"icon":"zap","text":"DC distribution & fusing"},{"icon":"sun","text":"Solar / battery circuit support"},{"icon":"shield-check","text":"Neat routing & safety checks"}]', 'Compliance-minded workmanship\nClear diagrams on request\nWorks with your installer plan', 4000.00, 'Site assessment from LKR 4,000', 'assets/images/services/svc-electrical.svg', 'zap', 7, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  short_description = VALUES(short_description),
  description = VALUES(description),
  what_we_do = VALUES(what_we_do),
  features_json = VALUES(features_json),
  benefits_text = VALUES(benefits_text),
  price_from = VALUES(price_from),
  price_note = VALUES(price_note),
  cover_image = VALUES(cover_image),
  lucide_icon = VALUES(lucide_icon),
  sort_order = VALUES(sort_order),
  active = VALUES(active);

DELETE FROM web_service_images WHERE service_id BETWEEN 1 AND 7;

INSERT INTO web_service_images (service_id, image_path, caption, sort_order) VALUES
(1, 'assets/images/services/svc-computer.svg', 'Laptop repair & diagnostics', 0),
(1, 'assets/images/services/svc-computer.svg', 'Motherboard & upgrade work', 1),
(1, 'assets/images/services/svc-computer.svg', 'Workshop cleaning & assembly', 2),
(2, 'assets/images/services/svc-printer.svg', 'Cartridge & toner service', 0),
(2, 'assets/images/services/svc-printer.svg', 'Printer repair & jam fixes', 1),
(2, 'assets/images/services/svc-printer.svg', 'Office printer maintenance', 2),
(3, 'assets/images/services/svc-cctv.svg', 'Camera mounting & alignment', 0),
(3, 'assets/images/services/svc-cctv.svg', 'DVR / NVR setup', 1),
(3, 'assets/images/services/svc-cctv.svg', 'Cable routing & power', 2),
(4, 'assets/images/services/svc-maintenance.svg', 'Scheduled health checks', 0),
(4, 'assets/images/services/svc-maintenance.svg', 'AMC visit documentation', 1),
(4, 'assets/images/services/svc-maintenance.svg', 'Multi-site support planning', 2),
(5, 'assets/images/services/svc-automobile.svg', 'Roadside assistance', 0),
(5, 'assets/images/services/svc-automobile.svg', 'Breakdown assessment', 1),
(5, 'assets/images/services/svc-automobile.svg', 'Battery & electrical', 2),
(6, 'assets/images/services/svc-ac.svg', 'AC cleaning & service', 0),
(6, 'assets/images/services/svc-ac.svg', 'Gas & performance check', 1),
(6, 'assets/images/services/svc-ac.svg', 'Split unit maintenance', 2),
(7, 'assets/images/services/svc-electrical.svg', 'DC wiring work', 0),
(7, 'assets/images/services/svc-electrical.svg', 'Safe terminations & routing', 1),
(7, 'assets/images/services/svc-electrical.svg', 'Auxiliary & CCTV power', 2);

ALTER TABLE web_services AUTO_INCREMENT = 8;
