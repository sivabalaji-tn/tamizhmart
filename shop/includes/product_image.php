<?php
/**
 * TamizhMart — Product Image Helper
 * Include this in any page that displays product images.
 *
 * Usage: getProductImgSrc($product)
 * Returns the correct src attribute value:
 *   - Full URL if image_url is set
 *   - Relative path if image filename is set
 *   - Empty string if neither
 */
function getProductImgSrc($product, $base = '../assets/uploads/products/') {
    // Priority 1: image_url (external URL — no storage used)
    if (!empty($product['image_url'])) {
        return htmlspecialchars($product['image_url']);
    }
    // Priority 2: uploaded file
    if (!empty($product['image'])) {
        $img = $product['image'];
        // Handle old data where URL was stored in image column
        if (strpos($img, 'http') === 0) return htmlspecialchars($img);
        return $base . htmlspecialchars($img);
    }
    return '';
}

function hasProductImg($product) {
    return !empty($product['image_url']) || !empty($product['image']);
}
?>