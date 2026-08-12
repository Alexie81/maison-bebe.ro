INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.name='accountant' AND p.name IN ('accounting_stock.settings','accounting_periods.manage');
