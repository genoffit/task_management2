INSERT INTO departments (name, description) VALUES 
('HR', 'Human Resources'),
('Finance', 'Financial Department');

INSERT INTO categories (name, description, color) VALUES 
('Feature', 'New feature development', '#00ff00'),
('Meeting', 'Meeting tasks', '#0000ff');

INSERT INTO users (name, username, email, password, department_id, role, status) VALUES 
('Manager', 'manager', 'manager@example.com', '$2y$10$' . password_hash('manager123', PASSWORD_DEFAULT), 1, 'manager', 'active'),
('User', 'user', 'user@example.com', '$2y$10$' . password_hash('user123', PASSWORD_DEFAULT), 2, 'user', 'active');

INSERT INTO tasks (title, description, department_id, category_id, assignee_id, priority, start_date, due_date, status) VALUES 
('Fix Bug', 'Fix login issue', 1, 1, 2, 'high', '2025-04-20', '2025-04-25', 'pending'),
('Team Meeting', 'Weekly sync', 2, 3, 3, 'medium', '2025-04-22', '2025-04-23', 'completed');