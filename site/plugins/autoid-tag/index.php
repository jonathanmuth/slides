<?php

Kirby::plugin('jonathanmuth/autoid-tag', [
  'tags' => [
    'note' => [
      'attr' => array(
        'autoid'
      ),
      'html' => function($tag) {
        $notealias = $tag->attr('note');
        $autoid = $tag->attr('autoid');
        $note = site()->index()->findBy('autoid', $autoid);

        if (empty($note)) {

          return '<a href="#"><span class=""><i class=""></i> Note Could not be Found</span></a>';

        } else {

          return '<a href="' . $note->url() . '"><span class="">' . $notealias . '</span></a>';

        }
      }
    ]
  ]
]);
