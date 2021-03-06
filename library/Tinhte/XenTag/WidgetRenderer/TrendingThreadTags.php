<?php

class Tinhte_XenTag_WidgetRenderer_TrendingThreadTags extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('tinhte_xentag_trending');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => '[Tinhte] XenTag - Trending Thread Tags',
            'options' => array(
                'forums' => XenForo_Input::ARRAY_SIMPLE,
                'days' => XenForo_Input::UINT,
                'limit' => XenForo_Input::UINT
            ),
            'useCache' => true,
            'cacheSeconds' => 3600, // cache for 1 hour
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'tinhte_xentag_widget_trending_thread_tags_options';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $params = $template->getParams();

        $forums = $this->_helperPrepareForumsOptionSource(empty($params['options']['forums'])
            ? array() : $params['options']['forums'], true);
        $template->setParam('forums', $forums);

        return parent::_renderOptions($template);
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        if ('days' == $optionKey) {
            if (empty($optionValue)) {
                $optionValue = Tinhte_XenTag_Option::get('trendingDays');
            }
        } elseif ('limit' == $optionKey) {
            if (empty($optionValue)) {
                $optionValue = Tinhte_XenTag_Option::get('trendingMax');
            }
        }

        return true;
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'tinhte_xentag_widget_trending';
    }

    protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $template)
    {
        $core = WidgetFramework_Core::getInstance();
        /** @var Tinhte_XenTag_XenForo_Model_Tag $tagModel */
        $tagModel = $core->getModelFromCache('XenForo_Model_Tag');

        if (!empty($widget['options']['days'])) {
            $days = $widget['options']['days'];
        } else {
            $days = Tinhte_XenTag_Option::get('trendingDays');
        }
        $cutoff = XenForo_Application::$time - $days * 86400;

        if (!empty($widget['options']['limit'])) {
            $limit = $widget['options']['limit'];
        } else {
            $limit = Tinhte_XenTag_Option::get('trendingMax');
        }

        $forumIds = array();
        if (!empty($widget['options']['forums'])) {
            $forumIds = $this->_helperGetForumIdsFromOption($widget['options']['forums'], $params, true);
        }

        $db = XenForo_Application::getDb();
        $counts = $db->fetchPairs('
			SELECT tag_content.tag_id, COUNT(*) AS tagged_count
			FROM `xf_tag_content` AS tag_content
			' . (!empty($forumIds) ? 'INNER JOIN `xf_thread` AS thread
				ON (thread.thread_id = tag_content.content_id)' : '') . '
			WHERE tag_content.content_type = "thread" AND tag_content.add_date > ?
				' . (!empty($forumIds) ? 'AND thread.node_id IN (' . $db->quote($forumIds) . ')' : '') . '
			GROUP BY tag_content.tag_id
			ORDER BY tagged_count DESC
			LIMIT ?;
		', array(
            $cutoff,
            $limit
        ));

        $tags = array();
        if (!empty($counts)) {
            $tagsDb = $tagModel->fetchAllKeyed('
                SELECT *
                FROM `xf_tag`
                WHERE tag_id IN (' . $db->quote(array_keys($counts)) . ')
            ', 'tag_id');

            foreach ($counts as $tagId => $count) {
                if (isset($tagsDb[$tagId])) {
                    $tags[$tagId] = $tagsDb[$tagId];
                    $tags[$tagId]['use_count'] = $count;
                }
            }
        }

        $tagsLevels = $tagModel->getTagCloudLevels($tags);

        $template->setParam('tags', $tags);
        $template->setParam('tagsLevels', $tagsLevels);

        return $template->render();
    }

    protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array())
    {
        if (!empty($widget['options']['forums'])) {
            $forumIds = $this->_helperGetForumIdsFromOption($widget['options']['forums'], $params, true);
            if (!empty($forumIds)) {
                $suffix[] = md5(serialize($forumIds));
            }
        }

        return parent::_getCacheId($widget, $positionCode, $params, $suffix);
    }

}
