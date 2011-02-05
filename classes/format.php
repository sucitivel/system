<?php
/**
 * @package Habari
 *
 */

/**
 * Habari Format Class
 *
 * Provides formatting functions for use in themes.  Extendable.
 *
 */
class Format
{
	private static $formatters = null;

	/**
	 * Called to register a format function to a plugin hook, only passing the hook's first parameter to the Format function.
	 * @param string $format A function name that exists in a Format class
	 * @param string $onwhat A plugin hook to apply that Format function to as a filter
	 */
	public static function apply( $format, $onwhat )
	{
		if ( self::$formatters == null ) {
			self::load_all();
		}

		foreach ( self::$formatters as $formatobj ) {
			if ( method_exists( $formatobj, $format ) ) {
				$index = array_search( $formatobj, self::$formatters );
				$func = '$o = Format::by_index(' . $index . ');return $o->' . $format . '($a';
				$args = func_get_args();
				if ( count( $args ) > 2 ) {
					$func.= ', ';
					$args = array_map( create_function( '$a', 'return "\'{$a}\'";' ), array_slice( $args, 2 ) );
					$func .= implode( ', ', $args );
				}
				$func .= ');';
				$lambda = create_function( '$a', $func );
				Plugins::register( $lambda, 'filter', $onwhat );
				break;  // We only look for one matching format function to apply.
			}
		}
	}

	/**
	 *
	 *
	 */
	public static function apply_with_hook_serialize( $arg )
	{
		$arg = serialize( $arg );
		return "'{$arg}'";
	}

	public static function apply_with_hook_unserialize( $arg )
	{
		$arg = unserialize( $arg );
		return $arg;
	}

	/**
	 * Called to register a format function to a plugin hook, and passes all of the hook's parameters to the Format function.
	 * @param string $format A function name that exists in a Format class
	 * @param string $onwhat A plugin hook to apply that Format function to as a filter
	 */
	public static function apply_with_hook_params( $format, $onwhat )
	{
		if ( self::$formatters == null ) {
			self::load_all();
		}

		foreach ( self::$formatters as $formatobj ) {
			if ( method_exists( $formatobj, $format ) ) {
				$index = array_search( $formatobj, self::$formatters );
				$func = '$o = Format::by_index(' . $index . ');';
				$func .= '$args = func_get_args();';
				$func .= '$args = array_merge( $args';
				$args = func_get_args();
				if ( count( $args ) > 2 ) {

					$func .= ', array_map( array( "Format", "apply_with_hook_unserialize" ),';
					$args = array_map( array( "Format", "apply_with_hook_serialize" ), array_slice( $args, 2 ) );
					$func .= 'array( ' . implode( ', ', $args ) . ' ))';
				}
				$func .= ');';
				$func .= 'return call_user_func_array(array($o, "' . $format . '"), $args);';
				$lambda = create_function( '$a', $func );
				Plugins::register( $lambda, 'filter', $onwhat );
				break;  // We only look for one matching format function to apply.
			}
		}
	}

	/**
	 * function by_index
	 * Returns an indexed formatter object, for use by lambda functions created
	 * to supply additional parameters to plugin filters.
	 * @param integer $index The index of the formatter object to return.
	 * @return Format The formatter object requested
	 */
	public static function by_index( $index )
	{
		return self::$formatters[$index];
	}

	/**
	 * function load_all
	 * Loads and stores an instance of all declared Format classes for future use
	 */
	public static function load_all()
	{
		self::$formatters = array();
		$classes = get_declared_classes();
		foreach ( $classes as $class ) {
			if ( ( get_parent_class( $class ) == 'Format' ) || ( $class == 'Format' ) ) {
				self::$formatters[] = new $class();
			}
		}
		self::$formatters = array_merge( self::$formatters, Plugins::get_by_interface( 'FormatPlugin' ) );
		self::$formatters = array_reverse( self::$formatters, true );
	}

	/** DEFAULT FORMAT FUNCTIONS **/

	/**
	 * function autop
	 * Converts non-HTML paragraphs separated with 2 or more new lines into HTML paragraphs
	 * while preserving any internal HTML.
	 * New lines within the text of block elements are converted to linebreaks.
	 * New lines before and after tags are stripped.
	 *
	 * If you make changes to this, PLEASE add test cases here:
	 *   http://svn.habariproject.org/habari/trunk/tests/data/autop/
	 *
	 * @param string $value The string to apply the formatting
	 * @returns string The formatted string
	 */
	public static function autop( $value )
	{
		$value = str_replace( "\r\n", "\n", $value );
		$value = trim( $value );
		$ht = new HtmlTokenizer( $value, false );
		$set = $ht->parse();
		$value = '';

		// should never autop ANY content in these items
		$no_auto_p = array(
			'pre','code','ul','h1','h2','h3','h4','h5','h6',
			'table','ul','ol','li','i','b','em','strong'
		);

		$block_elements = array(
			'address','blockquote','center','dir','div','dl','fieldset','form',
			'h1','h2','h3','h4','h5','h6','hr','isindex','menu','noframes',
			'noscript','ol','p','pre','table','ul'
		);

		$token = $set->current();

		// There are no tokens in the text being formatted
		if ( $token === false ) {
			return $value;
		}

		$open_p = false;
		do {

			if ( $open_p ) {
				if ( ( $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_EMPTY || $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_OPEN || $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_CLOSE ) && in_array( strtolower( $token['name'] ), $block_elements ) ) {
					if ( strtolower( $token['name'] ) != 'p' || $token['type'] != HTMLTokenizer::NODE_TYPE_ELEMENT_CLOSE ) {
						$value .= '</p>';
					}
					$open_p = false;
				}
			}

			if ( $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_OPEN && !in_array( strtolower( $token['name'] ), $block_elements ) && $value == '' ) {
				// first element, is not a block element
				$value = '<p>';
				$open_p = true;
			}

			// no-autop, pass them through verbatim
			if ( $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_OPEN && in_array( strtolower( $token['name'] ), $no_auto_p ) ) {
				$nested_token = $token;
				do {
					$value .= HtmlTokenSet::token_to_string( $nested_token, false );
					if (
						( $nested_token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_CLOSE
							&& strtolower( $nested_token['name'] ) == strtolower( $token['name'] ) ) // found closing element
					) {
						break;
					}
				} while ( $nested_token = $set->next() );
				continue;
			}

			// anything that's not a text node should get passed through
			if ( $token['type'] != HTMLTokenizer::NODE_TYPE_TEXT ) {
				$value .= HtmlTokenSet::token_to_string( $token, true );
				// If the token itself is p, we need to set $open_p
				if ( strtolower( $token['name'] ) == 'p' && $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_OPEN ) {
					$open_p = true;
				}
				continue;
			}

			// if we get this far, token type is text
			$local_value = $token['value'];
			if ( MultiByte::strlen( $local_value ) ) {
				if ( !$open_p ) {
					$local_value = '<p>' . ltrim( $local_value );
					$open_p = true;
				}

				$local_value = preg_replace( '/\s*(\n\s*){2,}/u', "</p><p>", $local_value ); // at least two \n in a row (allow whitespace in between)
				$local_value = str_replace( "\n", "<br>", $local_value ); // nl2br
			}
			$value .= $local_value;
		} while ( $token = $set->next() );

		$value = preg_replace( '#\s*<p></p>\s*#u', '', $value ); // replace <p></p>
		$value = preg_replace( '/<p><!--(.*?)--><\/p>/', "<!--\\1-->", $value ); // replace <p></p> around comments
		if ( $open_p ) {
			$value .= '</p>';
		}

		return $value;
	}

	/**
	 * function and_list
	 * Turns an array of strings into a friendly delimited string separated by commas and an "and"
	 * @param array $array An array of strings
	 * @param string $between Text to put between each element
	 * @param string $between_last Text to put between the next-to-last element and the last element
	 * @reutrn string The constructed string
	 */
	public static function and_list( $array, $between = ', ', $between_last = null )
	{
		if ( ! is_array( $array ) ) {
			$array = array( $array );
		}

		if ( $between_last === null ) {
			$between_last = _t( ' and ' );
		}

		$last = array_pop( $array );
		$out = implode( ', ', $array );
		$out .= ($out == '') ? $last : $between_last . $last;
		return $out;
	}

	/**
	 * function tag_and_list
	 * Formatting function (should be in Format class?)
	 * Turns an array of tag names into an HTML-linked list with commas and an "and".
	 * @param array $array An array of tag names
	 * @param string $between Text to put between each element
	 * @param string $between_last Text to put between the next to last element and the last element
	 * @return string HTML links with specified separators.
	 */
	public static function tag_and_list( $terms, $between = ', ', $between_last = null )
	{
		$array = array();
		if ( !$terms instanceof Terms ) {
			$terms = new Terms( $terms );
		}

		foreach ( $terms as $term ) {
			$array[$term->term] = $term->term_display;
		}

		if ( $between_last === null ) {
			$between_last = _t( ' and ' );
		}

		$fn = create_function( '$a,$b', 'return "<a href=\\"" . URL::get("display_entries_by_tag", array( "tag" => $b) ) . "\\" rel=\\"tag\\">" . $a . "</a>";' );
		$array = array_map( $fn, $array, array_keys( $array ) );
		$last = array_pop( $array );
		$out = implode( $between, $array );
		$out .= ( $out == '' ) ? $last : $between_last . $last;
		return $out;

	}

	/**
	 * Format a date using a specially formatted string
	 * Useful for using a single string to format multiple date components.
	 * Example:
	 *  If $dt is a HabariDateTime for December 10, 2008...
	 *  echo $dt->format_date('<div><span class="month">{F}</span> {j}, {Y}</div>');
	 *  // Output: <div><span class="month">December</span> 10, 2008</div>
	 *
	 * @param HabariDateTime $date The date to format
	 * @param string $format A string with date()-like letters within braces to replace with date components
	 * @return string The formatted string
	 */
	public static function format_date( $date, $format )
	{
		if ( !( $date instanceOf HabariDateTime ) ) {
			$date = HabariDateTime::date_create( $date );
		}
		preg_match_all( '%\{(\w)\}%iu', $format, $matches );

		$components = array();
		foreach ( $matches[1] as $format_component ) {
			$components['{'.$format_component.'}'] = $date->format( $format_component );
		}
		return strtr( $format, $components );
	}

	/**
	 * function nice_date
	 * Formats a date using a date format string
	 * @param HabariDateTime A date as a HabariDateTime object
	 * @param string A date format string
	 * @returns string The date formatted as a string
	 */
	public static function nice_date( $date, $dateformat = 'F j, Y' )
	{
		if ( !( $date instanceOf HabariDateTime ) ) {
			$date = HabariDateTime::date_create( $date );
		}
		return $date->format( $dateformat );
	}

	/**
	 * function nice_time
	 * Formats a time using a date format string
	 * @param HabariDateTime A date as a HabariDateTime object
	 * @param string A date format string
	 * @returns string The time formatted as a string
	 */
	public static function nice_time( $date, $dateformat = 'H:i:s' )
	{
		if ( !( $date instanceOf HabariDateTime ) ) {
			$date = HabariDateTime::date_create( $date );
		}
		return $date->format( $dateformat );
	}

	/**
	 * Returns a shortened version of whatever is passed in.
	 * @param string $value A string to shorten
	 * @param integer $count Maximum words to display [100]
	 * @param integer $max_paragraphs Maximum paragraphs to display [1]
	 * @return string The string, shortened
	 */
	public static function summarize( $text, $count = 100, $max_paragraphs = 1 )
	{
		$ellipsis = '&hellip;';

		$showmore = false;

		$ht = new HtmlTokenizer($text, false);
		$set = $ht->parse();

		$stack = array();
		$para = 0;
		$token = $set->current();
		$summary = new HTMLTokenSet();
		$set->rewind();
		$remaining_words = $count;
		// $bail lets the loop end naturally and close all open elements without adding new ones.
		$bail = false;
		for ( $token = $set->current(); $set->valid(); $token = $set->next() ) {
			if ( !$bail && $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_OPEN ) {
				$stack[] = $token;
			}
			if ( !$bail ) {
				switch ( $token['type'] ) {
					case HTMLTokenizer::NODE_TYPE_TEXT:
						$words = preg_split( '/(\\s+)/u', $token['value'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
						// word count is doubled because spaces between words are captured as their own array elements via PREG_SPLIT_DELIM_CAPTURE
						$words = array_slice( $words, 0, $remaining_words * 2 );
						$remaining_words -= count( $words ) / 2;
						$token['value'] = implode( '', $words );
						if ( $remaining_words <= 0 ) {
							$token['value'] .= $ellipsis;
							$summary[] = $token;
							$bail = true;
						}
						else {
							$summary[] = $token;
						}
						break;
					case HTMLTokenizer::NODE_TYPE_ELEMENT_CLOSE;
						// don't handle this case here
						break;
					default:
						$summary[] = $token;
						break;
				}
			}
			if ( $token['type'] == HTMLTokenizer::NODE_TYPE_ELEMENT_CLOSE ) {
				do {
					$end = array_pop( $stack );
					$end['type'] = HTMLTokenizer::NODE_TYPE_ELEMENT_CLOSE;
					$end['attrs'] = null;
					$end['value'] = null;
					$summary[] = $end;
				} while ( ( $bail || $end['name'] != $token['name'] ) && count( $stack ) > 0 );
				if ( count( $stack ) == 0 ) {
					$para++;
				}
				if ( $bail || $para >= $max_paragraphs ) {
					break;
				}
			}
		}

		return (string) $summary;
	}

	/**
	 * Returns a truncated version of post content when the post isn't being displayed on its own.
	 * Posts are split either at the comment <!--more--> or at the specified maximums.
	 * Use only after applying autop or other paragrpah styling methods.
	 * Apply to posts using:
	 * <code>Format::apply_with_hook_params( 'more', 'post_content_out' );</code>
	 * @param string $content The post content
	 * @param Post $post The Post object of the post
	 * @param string $more_text The text to use in the "read more" link.
	 * @param integer $max_words null or the maximum number of words to use before showing the more link
	 * @param integer $max_paragraphs null or the maximum number of paragraphs to use before showing the more link
	 * @return string The post content, suitable for display
	 */
	public static function more( $content, $post, $properties = array() )
	{
		// If the post requested is the post under consideration, always return the full post
		if ( $post->slug == Controller::get_var( 'slug' ) ) {
			return $content;
		}
		elseif ( is_string( $properties ) ) {
			$args = func_get_args();
			$more_text = $properties;
			$max_words = ( isset( $args[3] ) ? $args[3] : null );
			$max_paragraphs = ( isset( $args[4] ) ? $args[4] : null );
			$paramstring = "";
		}
		else {
			$paramstring = "";
			$paramarray = Utils::get_params( $properties );

			$more_text = ( isset( $paramarray['more_text'] ) ? $paramarray['more_text'] : 'Read More' );
			$max_words = ( isset( $paramarray['max_words'] ) ? $paramarray['max_words'] : null );
			$max_paragraphs = ( isset( $paramarray['max_paragraphs'] ) ? $paramarray['max_paragraphs'] : null );

			if ( isset( $paramarray['title:before'] ) || isset( $paramarray['title'] ) || isset( $paramarray['title:after'] ) ) {
				$paramstring .= 'title="';

				if ( isset( $paramarray['title:before'] ) ) {
					$paramstring .= $paramarray['title:before'];
				}
				if ( isset( $paramarray['title'] ) ) {
					$paramstring .= $post->title;
				}
				if ( isset( $paramarray['title:after'] ) ) {
					$paramstring .= $paramarray['title:after'];
				}
				$paramstring .= '" ';
			}
			if ( isset( $paramarray['class'] ) ) {
				$paramstring .= 'class="' . $paramarray['class'] . '" ';
			}

		}
		$matches = preg_split( '/<!--\s*more\s*-->/isu', $content, 2, PREG_SPLIT_NO_EMPTY );
		if ( count( $matches ) > 1 ) {
			return ( $more_text != '' ) ? reset( $matches ) . ' <a ' . $paramstring . 'href="' . $post->permalink . '">' . $more_text . '</a>' : reset( $matches );
		}
		elseif ( isset( $max_words ) || isset( $max_paragraphs ) ) {
			$max_words = empty( $max_words ) ? 9999999 : intval( $max_words );
			$max_paragraphs = empty( $max_paragraphs ) ? 9999999 : intval( $max_paragraphs );
			$summary = Format::summarize( $content, $max_words, $max_paragraphs );
			if ( MultiByte::strlen( $summary ) >= MultiByte::strlen( $content ) ) {
				return $content;
			}
			else {
				if ( strlen( $more_text  ) ) {
					// Tokenize the summary and link
					$ht = new HTMLTokenizer( $summary );
					$summary_set = $ht->parse();
					$ht = new HTMLTokenizer( '<a ' . $paramstring . ' href="' . $post->permalink . '">' . $more_text . '</a>' );
					$link_set= $ht->parse();
					// Find out where to put the link
					$end = $summary_set->end();
					$key = $summary_set->key();
					// Inject the link
					$summary_set->insert( $link_set, $key );

					return (string)$summary_set;
				}
				else {
					return $summary;
				}
			}
		}

	return $content;
	}

	/**
	 * html_messages
	 * Creates an HTML unordered list of an array of messages
	 * @param array $notices a list of success messages
	 * @param array $errors a list of error messages
	 * @return string HTML output
	 */
	public static function html_messages( $notices, $errors )
	{
		$output = '';
		if ( count( $errors ) ) {
			$output.= '<ul class="error">';
			foreach ( $errors as $error ) {
				$output.= '<li>' . $error . '</li>';
			}
			$output.= '</ul>';
		}
		if ( count( $notices ) ) {
			$output.= '<ul class="success">';
			foreach ( $notices as $notice ) {
				$output.= '<li>' . $notice . '</li>';
			}
			$output.= '</ul>';
		}

		return $output;
	}

	/**
	 * humane_messages
	 * Creates JS calls to display session messages
	 * @param array $notices a list of success messages
	 * @param array $errors a list of error messages
	 * @return string JS output
	 */
	public static function humane_messages( $notices, $errors )
	{
		$output = '';
		if ( count( $errors ) ) {
			foreach ( $errors as $error ) {
				$error = addslashes( $error );
				$output .= "human_msg.display_msg(\"{$error}\");";
			}
		}
		if ( count( $notices ) ) {
			foreach ( $notices as $notice ) {
				$notice = addslashes( $notice );
				$output .= "human_msg.display_msg(\"{$notice}\");";
			}
		}

		return $output;
	}

	/**
	 * json_messages
	 * Creates a JSON list of session messages
	 * @param array $notices a list of success messages
	 * @param array $errors a list of error messages
	 * @return string JS output
	 */
	public static function json_messages( $notices, $errors )
	{
		$messages = array_merge( $errors, $notices );
		return json_encode( $messages );
	}

	/**
	 * function term_tree
	 * Create nested HTML lists from a hierarchical vocabulary.
	 *
	 * Turns Terms or an array of terms from a hierarchical vocabulary into a ordered HTML list with list items for each term.
	 * @param mixed $terms An array of Term objects or a Terms object.
	 * @param string $wrapper An sprintf formatted in which to wrap each term.
	 * @param string $startlist A string to put at the start of the list.
	 * @param string $endlist A string to put at the end of the list.
	 * @param function $display_callback A callback function to apply to earch term as it is displayed.
	 * @return string The transformed vocabulary.
	 */
	public static function term_tree( $terms, $wrapper = '<div>%s</div>', $startlist = '<ol class="tree">', $endlist = '</ol>', $display_callback = null )
	{
		$out = $startlist;

		if ( !$terms instanceof Terms ) {
			$terms = new Terms( $terms );
		}

		$stack = array();

		foreach ( $terms as $term ) {
			if(count($stack)) {
				if($term->mptt_left - end($stack)->mptt_left == 1) {
					$out .= $startlist;
				}
				while(count($stack) && $term->mptt_left > end($stack)->mptt_right) {
					$out .= '</li>'. $endlist. "\n";
					array_pop($stack);
				}
			}

			$out .= '<li>';
			$out .= sprintf( $wrapper, isset( $display_callback ) ? $display_callback( $term, $wrapper ) : $term->term_display );
			if($term->mptt_right - $term->mptt_left > 1) {
				$stack[] = $term;
			}
			else {
				$out .= '</li>' ."\n";
			}
		}
		while(count($stack)) {
			$out .= '</li>' . $endlist . "\n";
			array_pop($stack);
		}

		$out .= $endlist;
		return $out;
	}
}
?>
