<?php

class Tinhte_XenTag_Helper
{
	public static function unserialize($string)
	{
		$array = $string;

		if (!is_array($array))
		{
			$array = @unserialize($array);
		}

		if (empty($array))
		{
			$array = array();
		}

		return $array;
	}

	public static function utf8_strrpos($haystack, $needle, $offset)
	{
		if (UTF8_MBSTRING)
		{
			return mb_strrpos($haystack, $needle, $offset);
		}
		else
		{
			return strrpos($haystack, $needle, $offset);
		}
	}

	public static function utf8_stripos($haystack, $needle, $offset)
	{
		if (UTF8_MBSTRING)
		{
			return mb_stripos($haystack, $needle, $offset);
		}
		else
		{
			return stripos($haystack, $needle, $offset);
		}
	}

	public static function utf8_strripos($haystack, $needle, $offset)
	{
		if (UTF8_MBSTRING)
		{
			return mb_strripos($haystack, $needle, $offset);
		}
		else
		{
			return strripos($haystack, $needle, $offset);
		}
	}

	public static function explodeTags($tagsStr)
	{
		// sondh@2013-03-27
		// process the string manually to make sure unicode character works
		$len = utf8_strlen($tagsStr);
		$tags = array();

		$start = 0;
		$i = 0;
		while ($i <= $len)
		{
			if ($i < $len)
			{
				$char = utf8_substr($tagsStr, $i, 1);
			}
			else
			{
				$char = false;
			}

			if ($char === false OR preg_match('/^' . Tinhte_XenTag_Constants::REGEX_SEPARATOR . '$/', $char))
			{
				// this is a separator
				$tagLen = $i - $start;
				if ($tagLen > 0)
				{
					$tags[] = utf8_substr($tagsStr, $start, $tagLen);
				}

				// skip the separator for the next tag
				$start = $i + 1;
			}
			else
			{
				// this is some other character
			}

			$i++;
		}

		return $tags;
	}

	public static function isTagContainingSeparator($tagText)
	{
		// sondh@2012-08-12
		// we have to add the u modifier to have the regular expression interpreted as
		// unicode it's 2012 and PHP still doesn't handle unicode transparently... *sigh*
		return preg_match('/' . Tinhte_XenTag_Constants::REGEX_SEPARATOR . '/u', $tagText) == 1;
	}

	public static function getImplodedTagsFromThread($thread, $getLinks = false)
	{
		return self::_getImplodedTags($thread, Tinhte_XenTag_Constants::FIELD_THREAD_TAGS, $getLinks);
	}

	public static function getImplodedTagsFromPage($page, $getLinks = false)
	{
		return self::_getImplodedTags($page, Tinhte_XenTag_Constants::FIELD_PAGE_TAGS, $getLinks);
	}

	public static function getImplodedTagsFromForum($forum, $getLinks = false)
	{
		return self::_getImplodedTags($forum, Tinhte_XenTag_Constants::FIELD_FORUM_TAGS, $getLinks);
	}

	public static function getImplodedTagsFromResource($resource, $getLinks = false)
	{
		return self::_getImplodedTags($resource, Tinhte_XenTag_Constants::FIELD_RESOURCE_TAGS, $getLinks);
	}

	protected static function _getImplodedTags($data, $key, $getLinks = false)
	{
		$result = array();

		if (is_array($data) AND isset($data[$key]))
		{
			$tagsOrTexts = self::unserialize($data[$key]);
		}
		else
		{
			$tagsOrTexts = array();
		}
		
		$tagTexts = self::getTextsFromTagsOrTexts($tagsOrTexts);
		
		if ($getLinks)
		{
			foreach ($tagTexts as $tagText)
			{
				$result[] = '<a href="' . XenForo_Link::buildPublicLink('tags', $tagText) . '">' . htmlspecialchars($tagText) . '</a>';
			}
		}
		else
		{
			foreach ($tagTexts as $tagText)
			{
				$result[] = htmlspecialchars($tagText);
			}
		}

		return implode(', ', $result);
	}

	public static function getOption($key)
	{
		return Tinhte_XenTag_Option::get($key);
	}
	
	public static function getTextsFromTagsOrTexts(array $tagsOrTexts)
	{
		$tagTexts = array();
		
		foreach ($tagsOrTexts as $entry)
		{
			if (is_string($entry))
			{
				$tagTexts[] = $entry;
			}
			elseif (is_array($entry) AND !empty($entry['tag_text']))
			{
				$tagTexts[] = $entry['tag_text'];
			}
		}
		
		return $tagTexts;
	}

	public static function getSafeTagsTextArrayForSearch(array $tagTexts)
	{
		$safe = array();

		foreach ($tagTexts as $tagText)
		{
			// sondh@2013-04-01
			// update to use md5 between dashes to support unicode tag text
			// the dashes are needed to bypass elasticsearch's analyzers (e.g. snowball)
			$safe[] = self::getSafeTagTextForSearch($tagText);
		}

		return $safe;
	}

	public static function getSafeTagsTextArrayForSearchMapping(array $tagTexts)
	{
		$safe = array();

		foreach ($tagTexts as $tagText)
		{
			// sondh@2013-04-01
			// update to use md5 between dashes to support unicode tag text
			// the dashes are needed to bypass elasticsearch's analyzers (e.g. snowball)
			$safe[self::getSafeTagTextForSearch($tagText)] = $tagText;
		}

		return $safe;
	}

	public static function getSafeTagTextForSearch($tagText)
	{
		switch (Tinhte_XenTag_Option::get('searchIndexType'))
		{
			case 'plaintext':
				return self::getNormalizedTagText($tagText);
			case 'md5':
				return md5(self::getNormalizedTagText($tagText));
			default:
				return sprintf('_%s_', md5(self::getNormalizedTagText($tagText)));
		}
	}

	public static function getNormalizedTagText($tagText)
	{
		$tagText = utf8_trim($tagText);
		$tagText = utf8_strtolower($tagText);

		return $tagText;
	}

}
