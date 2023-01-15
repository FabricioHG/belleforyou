<?php

namespace Drupal\transliterate_twig\TwigExtension;

use Drupal\Component\Transliteration\PhpTransliteration;

class TransliterateFilter extends \Twig_Extension {

  /**
   * Generates a list of all Twig filters that this extension defines.
   */
  public function getFilters() {
    return [
      new \Twig_SimpleFilter('tl', array($this, 'transliterateString')),
    ];
  }

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName() {
    return 'transliterate_twig.twig_extension';
  }

  /**
   * Transliterates a string
   */
  public static function transliterateString($string) {
    return (new PhpTransliteration())->transliterate($string);
  }

}
