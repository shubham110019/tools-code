function register_image_generation_endpoint()
{
    register_rest_route('custom/v1', '/generate-image/', array(
        'methods' => 'GET',
        'callback' => 'generate_image_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_image_generation_endpoint');

function generate_image_callback($data)
{
    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Get parameters
    $width = isset($_GET['width']) ? intval($_GET['width']) : 600;
    $height = isset($_GET['height']) ? intval($_GET['height']) : 400;
    $textColor = isset($_GET['textcolor']) ? $_GET['textcolor'] : '000000';
    $bgColor = isset($_GET['bgcolor']) ? $_GET['bgcolor'] : 'dddddd'; // Default background color light gray
    $text = isset($_GET['text']) ? sanitize_text_field($_GET['text']) : ''; // Default to empty string if no text provided

    // Create the image
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
        return new WP_REST_Response('Failed to create image.', 500);
    }

    // Allocate colors
    $bgColorAlloc = imagecolorallocate($image, hexdec(substr($bgColor, 0, 2)), hexdec(substr($bgColor, 2, 2)), hexdec(substr($bgColor, 4, 2)));
    $textColorAlloc = imagecolorallocate($image, hexdec(substr($textColor, 0, 2)), hexdec(substr($textColor, 2, 2)), hexdec(substr($textColor, 4, 2)));

    // Fill main image background
    imagefill($image, 0, 0, $bgColorAlloc);

    // Set font parameters
    $fontPath = get_template_directory() . '/assets/fonts/roboto-regular.ttf'; // Correct path to your TTF file

    // Check if font file exists
    if (!file_exists($fontPath)) {
        imagedestroy($image);
        return new WP_REST_Response('Font file not found: ' . $fontPath, 500);
    }

    // Determine font size
    $maxFontSize = min($width / 10, $height / 10); // Adjust this ratio as needed
    $fontSize = $maxFontSize;

    // Determine which text to display
    $displayText = !empty($text) ? $text : ($width . 'x' . $height);

    // Calculate text bounding box and adjust font size if necessary
    do {
        $textBoundingBox = imagettfbbox($fontSize, 0, $fontPath, $displayText);
        $textWidth = abs($textBoundingBox[2] - $textBoundingBox[0]);
        $textHeight = abs($textBoundingBox[7] - $textBoundingBox[1]);

        // Reduce font size if text is too big
        if ($textWidth > $width || $textHeight > $height) {
            $fontSize--;
        }
    } while ($textWidth > $width || $textHeight > $height && $fontSize > 1);

    // Calculate text position (centered within the image)
    $textBoundingBox = imagettfbbox($fontSize, 0, $fontPath, $displayText); // Recalculate with final font size
    $textWidth = abs($textBoundingBox[2] - $textBoundingBox[0]);
    $textHeight = abs($textBoundingBox[7] - $textBoundingBox[1]);

    $textX = (int) (($width - $textWidth) / 2);
    $textY = (int) (($height + $textHeight) / 2);

    // Adjust y position to be baseline-adjusted
    $textY -= (int) $textBoundingBox[1];

    // Add text to the image
    $textAdded = imagettftext($image, $fontSize, 0, $textX, $textY, $textColorAlloc, $fontPath, $displayText);
    if (!$textAdded) {
        imagedestroy($image);
        return new WP_REST_Response('Failed to add text to image.', 500);
    }

    // Set headers to display image
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);

    exit(); // Ensure no other output is sent
}
