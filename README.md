# Bannerbear PHP Library

A PHP wrapper for the Bannerbear API - an image and video generation service.

## Documentation

Find the full API documentation [here](https://developers.bannerbear.com/)

## Requirements

PHP 7.4 or Higher

## Installation

Install the package with:

```sh
composer require yongfook/bannerbear
```

To use the bindings, load it via Autoload

```php
require_once('vendor/autoload.php');
```

## V5 API

The [V5 API](https://developers.bannerbear.com/v5/) is a new generation of the Bannerbear API. **V5 API keys do not work with V2 endpoints, and V2 API keys do not work with V5 endpoints** — you must use the right client class for your key.

For the **V5 API**, use `Bannerbear\V5\Client` (this section).
For the **legacy V2 API**, see [Usage](#usage) below — that section is unchanged.

### Table of Contents

- [Authentication (V5)](#authentication-v5)
- [Account (V5)](#account-v5)
- [Image Templates (V5)](#image-templates-v5)
- [Images (V5)](#images-v5)
- [Batches (V5)](#batches-v5)
- [Webhooks (V5)](#webhooks-v5)
- [Instant URLs (V5)](#instant-urls-v5)

### Authentication (V5)

```php
$bb = new Bannerbear\V5\Client("your V5 API key");
```

Or set `BANNERBEAR_API_KEY` and instantiate without arguments:

```php
$bb = new Bannerbear\V5\Client();
```

### Account (V5)

```php
$bb->account();
```

### Image Templates (V5)

V5 renames V2's `templates` resource to `image_templates`.

```php
$bb->list_image_templates(1);
$bb->get_image_template("template uid");
$bb->update_image_template("template uid", [
    "name" => "New Name",
    "description" => "...",
    "tags" => ["portrait"],
]);
```

### Images (V5)

V5's `modifications` is an **associative array** with two sub-keys:

- `template` — template-level changes (width, height, etc.)
- `objects` — array of per-layer changes (equivalent to V2's flat modifications array)

```php
$bb->create_image("template uid", [
    "modifications" => [
        "template" => ["width" => 1080, "height" => 1080],
        "objects" => [
            ["name" => "headline", "text" => "Hello World!"],
            [
                "name" => "photo",
                "image_url" => "https://images.unsplash.com/photo-1555400038-63f5ba517a47?w=1000&q=80",
            ],
        ],
    ],
]);
```

Synchronous generation routes to `sync.api.bannerbear.com/v5` (10s timeout). The 3rd positional `synchronous` argument is a transport switch — it is **not** sent in the request body:

```php
$bb->create_image("template uid", ["modifications" => ["objects" => [...]]], true);
```

##### Options for `create_image`

- `modifications`: V5 modifications array (`array`)
- `formats`: output formats, e.g. `["jpg", "pdf"]` (`array`)
- `scale`: scale multiplier, 1–4 (`integer`)
- `dpi`: DPI metadata (`integer`)
- `quality`: quality control (`integer`)
- `proxy`: proxy server for asset fetching (`string`)
- `metadata`: include any metadata to reference at a later point (`string`)
- `version`: pin template version (`integer`)
- 3rd positional `synchronous`: route to the sync host (`boolean`; SDK-only, not sent to the API)

```php
$bb->get_image("image uid");
$bb->list_images(1);
```

### Batches (V5)

Generate multiple images in one request (up to 100).

```php
$bb->create_batch([
    "type" => "image",
    "items" => [
        ["template" => "template uid 1", "modifications" => ["objects" => [...]]],
        ["template" => "template uid 2", "modifications" => ["objects" => [...]]],
    ],
]);
$bb->get_batch("batch uid");
$bb->list_batches(1);
```

### Webhooks (V5)

Webhooks are managed as a first-class resource in V5 (instead of being a per-request `webhook_url` parameter).

```php
$hook = $bb->create_webhook([
    "name" => "my-webhook",
    "url" => "https://example.com/hook",
    "resource" => "image",
    "event" => "completed",
    "status" => "active",
    "scope" => "all",
    "templates" => [],
]);

// IMPORTANT: signing_key is ONLY returned in the create response. Store it now —
// subsequent get_webhook calls will not include it.
echo $hook["signing_key"];
```

CRUD:

```php
$bb->get_webhook("webhook uid");
$bb->update_webhook("webhook uid", [
    "name" => "renamed",
    "url" => "https://example.com/hook",
    "resource" => "image",
    "event" => "completed",
    "status" => "active",
    "scope" => "all",
]);
$bb->delete_webhook("webhook uid");
$bb->list_webhooks(1);
```

### Instant URLs (V5)

Instant URLs are URLs bound to a template that can be manipulated with query strings — the V5 equivalent of V2's "Signed URLs" feature.

#### Create an Instant URL base

```php
$iurl = $bb->create_instant_url([
    "name" => "my-instant-url",
    "template" => "template uid",
    "mode" => "encoded",       // or "named_params"
    "security" => "signed",    // or "open"
    "status" => "active",
    "scale" => 1,              // 1, 2, 3, or 4
]);

// IMPORTANT: signing_key is ONLY returned in the create response. Store it now.
echo $iurl["signing_key"];
echo $iurl["base_url"];
```

##### Options for `create_instant_url` / `update_instant_url`

- `name` *required* (`string`)
- `template` *required* — image template UID (`string`)
- `mode`: `"encoded"` or `"named_params"` (`string`)
- `security`: `"signed"` or `"open"` (`string`)
- `status`: `"active"` or `"disabled"` (`string`)
- `scale`: 1, 2, 3, or 4 (`integer`)
- `rate_limit`: enable per-IP rate limiting (`boolean`)
- `template_version`: pin template version (`integer | null`)
- `max_renders`: cap total renders (`integer | null`)
- `expires_at`: ISO 8601 expiry (`string | null`)

CRUD:

```php
$bb->get_instant_url("uid");
$bb->update_instant_url("uid", ["name" => "...", "template" => "...", /* ... */]);
$bb->delete_instant_url("uid");
$bb->list_instant_urls(1);
```

#### Build an Instant URL with modifications

`build_instant_url` is a pure local helper — no API call. It composes the URL from a base + modifications and, if a signing key is provided, appends the HMAC signature.

```php
// Encoded mode, signed
$bb->build_instant_url($iurl["base_url"], [
    "mode" => "encoded",
    "signing_key" => $iurl["signing_key"],
    "modifications" => [
        "template" => ["width" => 1030, "height" => 890],
        "objects" => [["name" => "title", "text" => "Hello!", "color" => "#ffffff"]],
    ],
]);

// Named params mode, signed
$bb->build_instant_url($iurl["base_url"], [
    "mode" => "named_params",
    "signing_key" => $iurl["signing_key"],
    "modifications" => [
        "template" => ["width" => 1030, "height" => 890],
        "objects" => [["name" => "title", "text" => "Hello!"]],
    ],
]);

// Open (unsigned): omit signing_key
$bb->build_instant_url($iurl["base_url"], [
    "mode" => "encoded",
    "modifications" => ["objects" => [["name" => "title", "text" => "Hello!"]]],
]);
```

##### Options for `build_instant_url`

- `mode`: `"encoded"` (default) or `"named_params"` (`string`)
- `signing_key`: only needed when the instant URL was created with `security: "signed"` (`string`)
- `modifications`: same shape as `create_image`'s modifications (`array`)

---

## Usage

### Table of Contents

- [Authentication](#authentication)
- [Account Info](#account-info)
- [Images](#images)
- [Videos](#videos)
- [Collections](#collections)
- [Animated Gifs](#animated-gifs)
- [Movies](#movies)
- [Screenshots](#screenshots)
- [Templates](#templates)
- [Template Sets](#template-sets)
- [Video Templates](#video-templates)
- [Signed URLs](#signed-urls)

> Please note that all methods return dictionaries/associative arrays. Responses can be found in the developer documentation [here](https://developers.bannerbear.com/).

### Authentication

Get the API key for your project in Bannerbear and then instantiate a new client.

```php
$bb = new Bannerbear\BannerbearClient("your api key");
```

Alternatively, set the API key in an environment variable named `BANNERBEAR_API_KEY`.

```php
$bb = new Bannerbear\BannerbearClient();
```

### Account Info

Return info about the account or project associated with the API key.

```php
$account = $bb->account();
```

### Images

#### Create an Image

To create an image you reference a template uid and a list of modifications. The default is async generation meaning the API will respond with a `pending` status and you can use `get_image` to retrieve the final image.

```php
$bb->create_image("template uid", [
    "modifications" => [
        [
            "name" => "headline",
            "text" => "Hello world!",
        ],
        [
            "name" => "photo",
            "image_url" =>
            "https://images.unsplash.com/photo-1555400038-63f5ba517a47?w=1000&q=80",
        ],
    ],
]);
```

You can also create images synchronously - this will take longer to respond but the image will be delivered in the response:

```php
$bb->create_image(
  "template uid",
  [
    "modifications" => [
        [
            "name" => "headline",
            "text" => "Hello world!",
        ],
        [
            "name" => "photo",
            "image_url" =>
            "https://images.unsplash.com/photo-1555400038-63f5ba517a47?w=1000&q=80",
        ],
    ],
  ],
  TRUE
);
```

##### Options

- `modifications`: an array of [modifications](https://developers.bannerbear.com/#post-v2-images) you would like to make (`array`)
- `webhook_url`: a webhook url to post the final image object to (`string`)
- `transparent`: render image with a transparent background (`boolean`)
- `synchronous`: generate the image synchronously (`boolean`)
- `render_pdf`: render a PDF in addition to an image (`boolean`)
- `metadata`: include any metadata to reference at a later point (`string`)

#### Get an Image

```php
$bb->get_image("image uid");
```

#### List all images

```php
$bb->list_images();
```

Optionally you can provide a page and limit

```php
$bb->list_images(10, 25);
```

### Videos

#### Create a Video

```php
$bb->create_video("video template uid", [
  "input_media_url" => "https://www.yourserver.com/videos/awesome_video.mp4",
  "modifications" => [
    [
      "name" => "headline",
      "text" => "Hello world",
    ],
  ],
]);
```

##### Options

- `input_media_url`: a url to a publicly available video file you want to import (string)
- `modifications`: an array of modifications you would like to make to the video overlay (array)
- `webhook_url`: a webhook url to post the final video object to (string)
- `blur`: blur the imported video from 1-10 (integer)
- `trim_to_length_in_seconds`: trim the video to a specific length (integer)
- `create_gif_preview`: create a short preview gif (boolean)
- `metadata`: include any metadata to reference at a later point (string)

If your video is using the "Multi Overlay" build pack then you can pass in a set of frames to render via:

- `frames`: an array of sets of modifications (array)
- `frame_durations`: specify the duration of each frame (array)

#### Get a video

```php
$bb->get_video("video uid");
```

#### Update a Video

```php
$bb->update_video("video uid", [
  "approved" => true,
  "transcription" => [
    "This is a new transcription",
    "It must contain the same number of lines",
    "As the previous transcription",
  ],
]);
```

##### Options

- `approved`: approve the video for rendering (boolean)
- `transcription`: an array of strings to represent the new transcription (will overwrite the existing one) (array)

#### List all Videos

```php
$bb->list_videos();
```

##### Options

- `page`: pagination (`integer`)

### Collections

Create multiple images in one API request.

```php
$bb->get_collection("collection uid");
$bb->list_collections(3);
$bb->create_collection(
  "template set uid",
  [
    "modifications" => [
      [
        "name" => "headline",
        "text" => "Hello World!",
      ],
    ],
  ],
  true
);
```

##### Options for `create_collection`

- `modifications`: an array of [modifications](https://developers.bannerbear.com/#post-v2-images) you would like to make (`array`)
- `webhook_url`: a webhook url to post the final collection object to (`string`)
- `transparent`: render image with a transparent background (`boolean`)
- `synchronous`: generate the images synchronously (`boolean`)
- `metadata`: include any metadata to reference at a later point (`string`)

### Animated Gifs

Create a slideshow style gif

```php
$bb->get_animated_gif("gif uid")
$bb->list_animated_gifs(3)
$bb->create_animated_gif("template uid", [
    "frames" => [
        [ // frame 1 starts here
            [
                "name" => "layer1",
                "text" => "This is my text"
            ],
            [
                "name" => "photo",
                "image_url" => "https//www.pathtomyphoto.com/1.jpg"
            ]
        ],
        [ // frame 2 starts here
            [
                "name" => "layer1",
                "text" => "This is my follow up text"
            ],
            [
                "name" => "photo",
                "image_url" => "https://www.pathtomyphoto.com/2.jpg"
            ]
        ]
    ]
])
```

##### Options for `create_animated_gif`

- `frames`: an array of arrays of [modifications](https://developers.bannerbear.com/#post-v2-images) you would like to make (`array`)
- `frame_durations`: an array of times (in seconds) to show each frame (`array`)
- `input_media_url`: optionally import an external video file to use as part of the gif
- `fps`: frames per second e.g. 1 (`integer`)
- `loop`: whether to loop or not (`boolean`)
- `webhook_url`: a webhook url to post the final gif object to (`string`)
- `metadata`: include any metadata to reference at a later point (`string`)

### Movies

Assemble video clips or still images into a single movie with transitions.

```php
$bb->get_movie("movie uid");
$bb->list_movies(3);
$bb->create_movie([
  "width" => 800,
  "height" => 800,
  "transition" => "pixelize",
  "inputs" => [
    [
      "asset_url" => "https://images.unsplash.com/photo-1635910160061-4b688344bd20?w=500&q=60",
    ],
    [
      "asset_url" => "https://i.imgur.com/fH7a5dO.png",
    ],
  ],
]);
```

##### Options for `create_movie`

- `width`: the movie width in pixels (`integer`)
- `height`: the movie height in pixels (`integer`)
- `transition`: the transition style: fade, pixelize, slidedown, slideright, slideup, slideleft (`string`)
- `inputs`: a list of [inputs](https://developers.bannerbear.com/#post-v2-movies) (`array`)
- `webhook_url`: a webhook url to post the final movie object to (`string`)
- `metadata`: include any metadata to reference at a later point (`string`)

### Screenshots

Take screenshots of websites.

```php
$bb->get_screenshot("screenshot uid");
$bb->list_screenshots(3);
$bb->create_screenshot(
  "https://www.bannerbear.com/",
  [
    "width" => 1000,
  ],
  true
);
```

##### Options for `create_screenshot`

- `width`: the desired screenshot width in pixels (`integer`)
- `height`: the desired screenshot height in pixels (`integer`)
- `synchronous`: generate the screenshot synchronously (`boolean`)
- `mobile`: use a mobile user agent
- `webhook_url`: a webhook url to post the final screenshot object to (`string`)

### Templates

```php
$bb->get_template("template uid");
$bb->update_template("template uid", [
  "name" => "New Template Name",
  "tags" => ["portrait", "instagram"],
]);
// Page, Limit, Tag, Name
$bb->list_templates(1, 100, 'tag', 'name');
```

### Template Sets

```php
$bb->get_template_set("template set uid");
$bb->list_template_sets(2);
```

### Video Templates

```php
$bb->get_video_template("video template uid");
$bb->list_video_templates(2);
```

### Signed URLs

This gem also includes a convenient utility for generating signed urls. Authenticate as above, then:

```php
$bb->generate_signed_url("base uid", [ "modifications" => []]);

// example
$bb->generate_signed_url("A89wavQyY3Bebk3djP", [
    "modifications" => [
        [
            "name" => "country",
            "text" => "testing!",
        ],
        [
            "name" => "photo",
            "image_url" => "https://images.unsplash.com/photo-1638356435991-4c79b00ebef3?w=764&q=80",
        ],
    ],
]);
// => https://ondemand.bannerbear.com/signedurl/A89wavQyY3Bebk3djP/image.jpg?modifications=W3sibmFtZSI6ImNvdW50cnkiLCJ0ZXh0IjoidGVzdGluZyEifSx7Im5hbWUiOiJwaG90byIsImltYWdlX3VybCI6Imh0dHBzOi8vaW1hZ2VzLnVuc3BsYXNoLmNvbS9waG90by0xNjM4MzU2NDM1OTkxLTRjNzliMDBlYmVmMz93PTc2NCZxPTgwIn1d&s=40e7c9d4902b86ea83e0c400e57d7cc580534fd527e234d40a0c7ace589a16eb
```

## Contributing

Bug reports and pull requests are welcome on GitHub at https://github.com/yongfook/bannerbear-php. This project is intended to be a safe, welcoming space for collaboration, and contributors are expected to adhere to the [code of conduct](https://github.com/yongfook/bannerbear-ruby/blob/master/CODE_OF_CONDUCT.md).

## License

The gem is available as open source under the terms of the [MIT License](https://opensource.org/licenses/MIT).

## Code of Conduct

Everyone interacting in the Bannerbear project's codebases, issue trackers, chat rooms and mailing lists is expected to follow the [code of conduct](https://github.com/yongfook/bannerbear-ruby/blob/master/CODE_OF_CONDUCT.md).
