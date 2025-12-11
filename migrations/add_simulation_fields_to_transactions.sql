-- migrations/add_simulation_fields_to_transactions.sql
ALTER TABLE `transactions`
ADD COLUMN `sim_client_ip` VARCHAR(45) NULL,
ADD COLUMN `sim_client_mac` VARCHAR(17) NULL,
ADD COLUMN `sim_link_orig` VARCHAR(255) NULL,
ADD COLUMN `sim_link_login_only` VARCHAR(255) NULL,
ADD COLUMN `sim_chap_id` VARCHAR(16) NULL,
ADD COLUMN `sim_chap_challenge` VARCHAR(32) NULL;
