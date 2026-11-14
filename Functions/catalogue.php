<?php
/**
 * Brands and categories seeded by the installer
 */

/**
 * @return array brand_id => brand_name
 */
function brands_list(): array
{
    $sql = "SELECT `brand_id`, `brand_name` FROM `brands` WHERE `brand_name` NOT LIKE 'Show all brands%' ORDER BY `brand_name`";
    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    $brands = array();
    if ($row[0] != '00000') {
        foreach ($row as $brand) {
            $brands[$brand->brand_id] = $brand->brand_name;
        }
    }
    return $brands;
}

/**
 * @return array category_id => array(name, level)
 */
function categories_list(): array
{
    $sql = "SELECT `category_id`, `categories`, `level` FROM `categories` WHERE `in_moderation` = 0 ORDER BY `level`, `categories`";
    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    $categories = array();
    if ($row[0] != '00000') {
        foreach ($row as $category) {
            $categories[$category->category_id] = array("name" => $category->categories, "level" => $category->level);
        }
    }
    return $categories;
}

/**
 * @param $brand_name
 * @return int|null brand_id for an exact (case-insensitive) name match
 */
function brand_lookup($brand_name): ?int
{
    if (trim((string)$brand_name) == "") {
        return null;
    }

    $params = array(":a" => trim($brand_name));
    $sql = "SELECT `brand_id` FROM `brands` WHERE `brand_name` = :a";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    return $row[0] != '00000' ? (int)$row[0]->brand_id : null;
}

/**
 * @param $category_id
 * @return int|null category_id when it exists
 */
function category_lookup($category_id): ?int
{
    if (empty($category_id)) {
        return null;
    }

    $params = array(":a" => (int)$category_id);
    $sql = "SELECT `category_id` FROM `categories` WHERE `category_id` = :a";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    return $row[0] != '00000' ? (int)$row[0]->category_id : null;
}
