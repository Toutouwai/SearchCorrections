# Search Corrections

Suggests alternative words for a given input word.

This can be useful in a website search feature where the given search term produces no results, but an alternative spelling or stem of the term may produce results. 

The module has two methods intended for public use:

1. `findSimilarWords()`: this method suggests corrected spellings or similar alternatives for the given word based on words that exist in the website.

2. `stem()`: this method returns the [stem](https://en.wikipedia.org/wiki/Stemming) of the given word, which may give a full or partial match for a word within the website.

The module doesn't dictate any particular way of using it in a website search feature, but one possible approach is as follows. If a search produces no matching pages you can take the search term (or if multiple terms, split and then loop over each term) and use the module methods to find alternative words and/or the stem word. Then automatically perform a new search using the alternative word(s), and show a notice to the user, e.g.

> Your search for "begining" produced no matches. Including results for "beginning" and "begin".

## SearchCorrections::findSimilarWords()

This method creates a list of unique words (the "word list") that exist on the pages and fields that you define, and compares those words to a target word that you give it. The method returns an array of words that are sufficiently similar to the target word.

### Similarity

The method ranks similar words by calculating the [Levenshtein distance](https://en.wikipedia.org/wiki/Levenshtein_distance) from the target word.

Where several results have the same Levenshtein distance from the target word these are ordered so that results which have more letters in common with the target word at the start of the result word are higher in the order.

### Method arguments

`$target` `(string)` The input word.

`$selector` `(string)` A selector string to find the pages that the word list will be derived from.

`$fields` `(array)` An array of field names that the word list will be derived from.

`$options` `(array)` Optional: an array of options as described below.
* `minWordLength` `(int)` Words below this length will not be included in the word list. Default: `4`
* `lengthRange` `(int)` Words that are longer or shorter than the target word by more than this number will not be included in the word list. Default: `2`
* `expire` `(int)` The word list is cached for this number of seconds, to improve performance. Default: `3600`
* `maxChangePercent` `(int)` When the Levenshtein distance between a word and the target word is calculated, the distance is then converted into a percentage of changed letters relative to the target word. Words that have a higher percentage change than this value are not included in the results. Default: `50`
* `insertionCost` `(int)` This is an optional argument for the PHP `levenshtein()` function. See the [docs](https://www.php.net/manual/en/function.levenshtein.php) for details. Default: `1`
* `replacementCost` `(int)` This is an optional argument for the PHP `levenshtein()` function. See the [docs](https://www.php.net/manual/en/function.levenshtein.php) for details. Default: `1`
* `deletionCost` `(int)` This is an optional argument for the PHP `levenshtein()` function. See the [docs](https://www.php.net/manual/en/function.levenshtein.php) for details. Default: `1`


### Example of use

```php
// The input word that may need correcting
$target = 'dispraxia';

// Get the Search Corrections module
$sc = $modules->get('SearchCorrections');
// Define a selector string to find the pages that the word list will be derived from
$selector = "template=basic-page";
// Define an array of field names that the word list will be derived from
$flds = ['title', 'body'];
// Optional: override any of the default options
$options = ['maxChangePercent' => 55];

// Get an array of similar words that exist in the pages/fields you defined
// The return value is in the format $word => $levenshtein_distance
$results = $sc->findSimilarWords($target, $selector, $flds, $options);
```

Example result:

![sc-result](https://github.com/Toutouwai/SearchCorrections/assets/1538852/ff15d5de-b673-49b3-9153-f1d92daef527)

## SearchCorrections::stem()

This method uses [php-stemmer](https://github.com/wamania/php-stemmer) to return the [stem](https://en.wikipedia.org/wiki/Stemming) of the given word. As an example, "fish" is the stem of "fishing", "fished", and "fisher".

The returned stem may be the original given word in some cases. The stem is not necessarily a complete word, e.g. the stem of "argued" is "argu". 

If using the stem in a search you will probably want to use a [selector operator](https://processwire.com/docs/selectors/operators/) that can match partial words.

### Method arguments

`$word` `(string)` The input word.

`$language` `(string)` Optional: the language name in English. The valid options are shown below. Default: `english`
* catalan
* danish
* dutch
* english
* finnish
* french
* german
* italian
* norwegian
* portuguese
* romanian
* russian
* spanish
* swedish

### Example of use

```php
// The input word
$word = 'fishing';
// Get the Search Corrections module
$sc = $modules->get('SearchCorrections');
// Get the stem of the word
$stem = $sc->stem($word);
```
