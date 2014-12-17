<?php

namespace MVC\Models\Validators;

use MVC\Models\Validator;

class PostExistsValidator extends Validator {

    public $_errorMessage = "This post could not be found.";

    public function test($value, $context)
    {
        $postId = (int)$value;

        if ($postId > 0) {
            // https://tommcfarlin.com/wordpress-post-exists-by-id/
            return is_string(get_post_status($postId));
        }

        return true;
    }

}
