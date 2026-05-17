<?php

registerBlockType('dog-park/suggestion-form', [
    'render_callback' => ['DogPark_Visitor_Form', 'render_block']
]);