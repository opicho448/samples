ALTER TABLE attendees
  MODIFY COLUMN ticket_code VARCHAR(100) NULL DEFAULT NULL;

ALTER TABLE attendees
  ADD COLUMN registration_status ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending' AFTER payment_status,
  ADD COLUMN mpesa_transaction_id VARCHAR(255) DEFAULT NULL AFTER registration_status,
  ADD COLUMN payment_proof_path VARCHAR(500) DEFAULT NULL AFTER mpesa_transaction_id;

UPDATE attendees
SET registration_status = 'Confirmed'
WHERE ticket_code IS NOT NULL AND ticket_code != '';

UPDATE attendees
SET registration_status = 'Rejected'
WHERE registration_status = 'Pending' AND payment_status = 'Rejected';
