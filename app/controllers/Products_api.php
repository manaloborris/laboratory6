<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Api_controller.php';

class Products_api extends Api_controller
{
    public function index()
    {
        $this->api->require_method('GET');
        $this->authenticated_user();
        $products = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY created_at DESC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['data' => $products]);
    }

    public function create()
    {
        $this->api->require_method('POST');
        $this->authenticated_user();
        $product = $this->validated_product($this->api->body());

        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)',
            [$product['product_name'], $product['description'], $product['price'], $product['quantity']]
        );
        $id = $this->db->raw('SELECT LAST_INSERT_ID()')->fetchColumn();
        $created = $this->find_product($id);

        $this->api->respond(['message' => 'Product created.', 'data' => $created], 201);
    }

    public function update($id)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
        $this->api->require_method($method);
        $this->authenticated_user();
        $existing = $this->find_product($id);

        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $partial = $method === 'PATCH';
        $input = $this->api->body();
        $product = $this->validated_product($input, $partial, $existing);

        $this->db->raw(
            'UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?',
            [$product['product_name'], $product['description'], $product['price'], $product['quantity'], $id]
        );

        $this->api->respond(['message' => 'Product updated.', 'data' => $this->find_product($id)]);
    }

    public function delete($id)
    {
        $this->api->require_method('DELETE');
        $this->authenticated_user();

        if (!$this->find_product($id)) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->db->raw('DELETE FROM products WHERE id = ?', [$id]);
        $this->api->respond(['message' => 'Product deleted.']);
    }

    private function find_product($id)
    {
        return $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
    }

    private function validated_product($input, $partial = false, $existing = [])
    {
        $allowed = ['product_name', 'description', 'price', 'quantity'];
        $unknown = array_diff(array_keys($input), $allowed);

        if ($unknown || ($partial && !$input)) {
            $this->api->respond_error('Only product fields may be submitted, and PATCH needs at least one field.', 422);
        }

        if ($partial) {
            $input = array_merge($existing, $input);
        }

        foreach (['product_name', 'price', 'quantity'] as $required) {
            if (!array_key_exists($required, $input)) {
                $this->api->respond_error('Product name, price, and quantity are required.', 422);
            }
        }

        $description = $input['description'] ?? '';

        if (!is_scalar($input['product_name']) || !is_scalar($input['price']) || !is_scalar($input['quantity']) || !is_string($description)) {
            $this->api->respond_error('Product fields must be valid text or numbers.', 422);
        }

        $name = trim((string) $input['product_name']);
        $price = (string) $input['price'];
        $quantity = (string) $input['quantity'];
        $quantityValue = filter_var($quantity, FILTER_VALIDATE_INT);

        if ($name === '' || strlen($name) > 100 || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price) || $quantityValue === false || $quantityValue < 0) {
            $this->api->respond_error('Use a product name up to 100 characters, a price up to 99999999.99, and a whole-number quantity.', 422);
        }

        return [
            'product_name' => $name,
            'description' => $description,
            'price' => $price,
            'quantity' => $quantityValue,
        ];
    }
}