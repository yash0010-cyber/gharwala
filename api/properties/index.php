<?php
/**
 * Properties API Endpoints
 * Handles property listing, creation, update, and deletion
 */

require_once dirname(__DIR__, 2) . '/config/Database.php';
require_once dirname(__DIR__, 2) . '/includes/AuthService.php';
require_once dirname(__DIR__, 2) . '/includes/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/includes/ApiResponse.php';
require_once dirname(__DIR__, 2) . '/config/Logger.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = array_filter(explode('/', $path));
$propertyId = isset($parts[5]) && is_numeric($parts[5]) ? (int)$parts[5] : null;

try {
    AuthService::init();

    switch ($method) {
        case 'GET':
            handleGetProperties($propertyId);
            break;

        case 'POST':
            if (!AuthService::isAuthenticated()) {
                ApiResponse::unauthorized();
            }
            handleCreateProperty();
            break;

        case 'PUT':
            if (!AuthService::isAuthenticated()) {
                ApiResponse::unauthorized();
            }
            if (!$propertyId) {
                ApiResponse::error('Property ID required');
            }
            handleUpdateProperty($propertyId);
            break;

        case 'DELETE':
            if (!AuthService::isAuthenticated()) {
                ApiResponse::unauthorized();
            }
            if (!$propertyId) {
                ApiResponse::error('Property ID required');
            }
            handleDeleteProperty($propertyId);
            break;

        default:
            ApiResponse::error('Method not allowed', null, 405);
    }

} catch (Exception $e) {
    Logger::exception($e);
    ApiResponse::serverError();
}

/**
 * Get properties (list or single)
 */
function handleGetProperties(?int $propertyId): void {
    if ($propertyId) {
        // Get single property
        $property = Database::fetch(
            'SELECT p.*, u.first_name, u.last_name, u.email,
                    (SELECT COUNT(*) FROM reviews WHERE property_id = p.id) as review_count,
                    (SELECT AVG(rating) FROM reviews WHERE property_id = p.id) as avg_rating
             FROM properties p
             JOIN users u ON p.tenant_id = u.id
             WHERE p.id = :id',
            ['id' => $propertyId]
        );

        if (!$property) {
            ApiResponse::notFound('Property not found');
        }

        // Parse JSON fields
        $property['amenities'] = json_decode($property['amenities'], true) ?? [];
        $property['images'] = json_decode($property['images'], true) ?? [];

        ApiResponse::success($property);
    } else {
        // List properties with filters
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, (int)($_GET['per_page'] ?? 20));
        $offset = ($page - 1) * $perPage;

        $query = 'FROM properties WHERE status = "approved"';
        $params = [];

        // Apply filters
        if (isset($_GET['city'])) {
            $query .= ' AND city = :city';
            $params['city'] = Sanitizer::sanitizeString($_GET['city']);
        }

        if (isset($_GET['min_price'])) {
            $query .= ' AND price_per_night >= :min_price';
            $params['min_price'] = Sanitizer::sanitizeFloat($_GET['min_price']);
        }

        if (isset($_GET['max_price'])) {
            $query .= ' AND price_per_night <= :max_price';
            $params['max_price'] = Sanitizer::sanitizeFloat($_GET['max_price']);
        }

        if (isset($_GET['bedrooms'])) {
            $query .= ' AND bedrooms >= :bedrooms';
            $params['bedrooms'] = Sanitizer::sanitizeInt($_GET['bedrooms']);
        }

        if (isset($_GET['search'])) {
            $search = Sanitizer::sanitizeString($_GET['search']);
            $query .= ' AND (MATCH(title, description, location) AGAINST(:search IN BOOLEAN MODE))';
            $params['search'] = $search;
        }

        // Get total count
        $total = Database::fetch("SELECT COUNT(*) as count " . $query, $params);
        $total = $total['count'] ?? 0;

        // Get paginated results
        $query .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $params['limit'] = $perPage;
        $params['offset'] = $offset;

        $properties = Database::fetchAll(
            'SELECT p.*, u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM reviews WHERE property_id = p.id) as review_count,
                    (SELECT AVG(rating) FROM reviews WHERE property_id = p.id) as avg_rating ' . $query,
            $params
        );

        // Parse JSON fields
        foreach ($properties as &$property) {
            $property['amenities'] = json_decode($property['amenities'], true) ?? [];
            $property['images'] = json_decode($property['images'], true) ?? [];
        }

        ApiResponse::paginated($properties, $page, $perPage, $total);
    }
}

/**
 * Create new property
 */
function handleCreateProperty(): void {
    if (!AuthService::hasRole('tenant')) {
        ApiResponse::forbidden('Only tenants can create properties');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $userId = AuthService::getCurrentUserId();

    $errors = [];
    if (empty($input['title'])) $errors['title'] = 'Title is required';
    if (empty($input['location'])) $errors['location'] = 'Location is required';
    if (empty($input['price_per_night'])) $errors['price_per_night'] = 'Price is required';

    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }

    try {
        Database::execute(
            'INSERT INTO properties (tenant_id, title, description, location, city, country, price_per_night, bedrooms, bathrooms, max_guests, amenities, status, created_at, updated_at)
             VALUES (:tenant_id, :title, :description, :location, :city, :country, :price_per_night, :bedrooms, :bathrooms, :max_guests, :amenities, "pending", NOW(), NOW())',
            [
                'tenant_id' => $userId,
                'title' => Sanitizer::sanitizeString($input['title']),
                'description' => Sanitizer::sanitizeString($input['description'] ?? ''),
                'location' => Sanitizer::sanitizeString($input['location']),
                'city' => Sanitizer::sanitizeString($input['city'] ?? ''),
                'country' => Sanitizer::sanitizeString($input['country'] ?? ''),
                'price_per_night' => Sanitizer::sanitizeFloat($input['price_per_night']),
                'bedrooms' => Sanitizer::sanitizeInt($input['bedrooms'] ?? 1),
                'bathrooms' => Sanitizer::sanitizeInt($input['bathrooms'] ?? 1),
                'max_guests' => Sanitizer::sanitizeInt($input['max_guests'] ?? 1),
                'amenities' => json_encode($input['amenities'] ?? [])
            ]
        );

        $propertyId = Database::lastInsertId();
        ApiResponse::success(['id' => $propertyId], 'Property created successfully. Awaiting admin approval.', 201);

    } catch (Exception $e) {
        Logger::exception($e);
        ApiResponse::serverError('Failed to create property');
    }
}

/**
 * Update property
 */
function handleUpdateProperty(int $propertyId): void {
    $property = Database::fetch(
        'SELECT tenant_id FROM properties WHERE id = :id',
        ['id' => $propertyId]
    );

    if (!$property) {
        ApiResponse::notFound('Property not found');
    }

    if ($property['tenant_id'] !== AuthService::getCurrentUserId() && !AuthService::hasRole('admin')) {
        ApiResponse::forbidden('You can only update your own properties');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $updates = [];
        $params = ['id' => $propertyId];

        if (isset($input['title'])) {
            $updates[] = 'title = :title';
            $params['title'] = Sanitizer::sanitizeString($input['title']);
        }

        if (isset($input['description'])) {
            $updates[] = 'description = :description';
            $params['description'] = Sanitizer::sanitizeString($input['description']);
        }

        if (isset($input['price_per_night'])) {
            $updates[] = 'price_per_night = :price';
            $params['price'] = Sanitizer::sanitizeFloat($input['price_per_night']);
        }

        if (empty($updates)) {
            ApiResponse::success(['id' => $propertyId], 'No changes made');
        }

        $updates[] = 'updated_at = NOW()';
        Database::execute(
            'UPDATE properties SET ' . implode(', ', $updates) . ' WHERE id = :id',
            $params
        );

        ApiResponse::success(['id' => $propertyId], 'Property updated successfully');

    } catch (Exception $e) {
        Logger::exception($e);
        ApiResponse::serverError('Failed to update property');
    }
}

/**
 * Delete property
 */
function handleDeleteProperty(int $propertyId): void {
    $property = Database::fetch(
        'SELECT tenant_id FROM properties WHERE id = :id',
        ['id' => $propertyId]
    );

    if (!$property) {
        ApiResponse::notFound('Property not found');
    }

    if ($property['tenant_id'] !== AuthService::getCurrentUserId() && !AuthService::hasRole('admin')) {
        ApiResponse::forbidden('You can only delete your own properties');
    }

    try {
        Database::execute('DELETE FROM properties WHERE id = :id', ['id' => $propertyId]);
        ApiResponse::success(null, 'Property deleted successfully');

    } catch (Exception $e) {
        Logger::exception($e);
        ApiResponse::serverError('Failed to delete property');
    }
}
