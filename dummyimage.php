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
    $width = isset($_GET['width']) ? intval($_GET['width']) : 0;
    $height = isset($_GET['height']) ? intval($_GET['height']) : 0;
    $textColor = isset($_GET['textcolor']) ? $_GET['textcolor'] : '000000';
    $bgColor = isset($_GET['bgcolor']) ? $_GET['bgcolor'] : 'dddddd'; // Default background color light gray
    $text = isset($_GET['text']) ? sanitize_text_field($_GET['text']) : ''; // Default to empty string if no text provided
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'png'; // Default format

    // Default dimensions if only one is provided
    if ($width == 0 && $height > 0) {
        $width = $height;
    } elseif ($height == 0 && $width > 0) {
        $height = $width;
    } elseif ($width == 0 && $height == 0) {
        $width = 600; // Default width
        $height = 400; // Default height
    }

    // Preserve aspect ratio
    if ($width != 0 && $height != 0) {
        // Aspect ratio is preserved
    } elseif ($width == 0 && $height != 0) {
        $width = $height * (16 / 9); // Example ratio
    } elseif ($height == 0 && $width != 0) {
        $height = $width * (9 / 16); // Example ratio
    }

    // Create the image
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
        return new WP_REST_Response('Failed to create image.', 500);
    }

    // Allocate colors
    $bgColorAlloc = imagecolorallocate($image, hexdec(substr($bgColor, 0, 2)), hexdec(substr($bgColor, 2, 2)), hexdec(substr($bgColor, 4, 2)));
    $textColorAlloc = imagecolorallocate($image, hexdec(substr($textColor, 0, 2)), hexdec(substr($textColor, 2, 2)), hexdec(substr($textColor, 4, 2)));

    // Fill the background
    imagefill($image, 0, 0, $bgColorAlloc);

    // Set font parameters
    $fontPath = get_template_directory() . '/assets/fonts/roboto-regular.ttf'; // Correct path to your TTF file

    // Check if font file exists
    if (!file_exists($fontPath)) {
        imagedestroy($image);
        return new WP_REST_Response('Font file not found: ' . $fontPath, 500);
    }

    // Determine maximum font size
    $maxFontSize = min($width / 10, $height / 10); // Adjust this ratio as needed
    $fontSize = $maxFontSize;

    // Determine which text to display
    $displayText = !empty($text) ? $text : ($width . 'x' . $height);

    // Wrap text into multiple lines if necessary
    $textLines = wrap_text($displayText, $fontPath, $fontSize, $width);

    // Calculate total text height
    $totalTextHeight = count($textLines) * $fontSize * 1.2; // Adjust line height as needed

    // Calculate vertical position to center text
    $textY = (int) (($height - $totalTextHeight) / 2);

    // Add text to the image
    foreach ($textLines as $line) {
        $textBoundingBox = imagettfbbox($fontSize, 0, $fontPath, $line);
        $textWidth = abs($textBoundingBox[2] - $textBoundingBox[0]);
        $textX = (int) (($width - $textWidth) / 2); // Center horizontally

        // Add each line of text to the image
        $textAdded = imagettftext($image, $fontSize, 0, $textX, $textY + $fontSize, $textColorAlloc, $fontPath, $line);
        if (!$textAdded) {
            imagedestroy($image);
            return new WP_REST_Response('Failed to add text to image.', 500);
        }

        // Move to the next line
        $textY += $fontSize * 1.2; // Move down for the next line
    }

    // Set headers and output image
    switch ($format) {
        case 'jpeg':
        case 'jpg':
            header('Content-Type: image/jpeg');
            imagejpeg($image);
            break;
        case 'gif':
            header('Content-Type: image/gif');
            imagegif($image);
            break;
        default:
            header('Content-Type: image/png');
            imagepng($image);
            break;
    }

    imagedestroy($image);

    exit(); // Ensure no other output is sent
}

function wrap_text($text, $fontPath, $fontSize, $maxWidth)
{
    $wrappedText = [];
    $words = explode(' ', $text);
    $line = '';

    foreach ($words as $word) {
        $testLine = $line . $word . ' ';
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);

        // Ensure that text does not exceed the width of the image
        if ($bbox[2] > $maxWidth) {
            if (strlen($line) > 0) {
                $wrappedText[] = trim($line);
            }
            $line = $word . ' ';
        } else {
            $line = $testLine;
        }
    }

    if (strlen($line) > 0) {
        $wrappedText[] = trim($line);
    }

    return $wrappedText;
}
