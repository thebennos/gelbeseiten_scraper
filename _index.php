<?phpset_time_limit(0);ini_set('memory_limit', '1000M');session_start();header('Content-Type: text/plain');ob_start();echo date('Y-m-d H:i:s') . PHP_EOL;include_once(dirname(__FILE__) . '/_constants.php');include_once(dirname(__FILE__) . '/_functions.php');include_once(dirname(__FILE__) . '/_db.php');include_once('Zend\Http\Client.php');$letter = $letter_orig = 'A';$next_letter = false;$category_id = 0;$city_id = 0;$city_page = 1;$charset = 'utf-8';if (isset($_GET['letter']) && is_string($_GET['letter']) && !empty($_GET['letter'])){    $letter_orig = (string) $_GET['letter'];    $letter = urlencode($letter_orig);}if (!$categories_page = getHtml($link = SITE_CATEGORIES_URL)){    _die('CATEGORY PAGE NOT EXISTS. Exiting.' . PHP_EOL . $link . PHP_EOL);}$categories_page = phpQuery::newDocumentHTML($categories_page, $charset);$main_categories = $categories_page->find('#tabbed_1_content ul li a');//-------------------------------------------------------------------------------foreach ($main_categories as $main_category){    echo pq($main_category)->text() . PHP_EOL;}die();//-------------------------------------------------------------------------------foreach ($categories_letters as $c_letter){    $c_letter = pq($c_letter);    if ($c_letter->text() != $letter_orig)    {        continue;    }    $sec = 0;    $tmp = $c_letter->parent();    while (!$next_letter && $sec < 100)    {        $sec++;        $tmp = $tmp->next('li');        if (!(bool) $tmp->text())        {            break;        }        if ($tmp->find('a')->count())        {            $next_letter = $tmp->find('a');            break;        }    }}$categories_items = $categories_page->find('.linkListContainer a');if (!$categories_items->count()){    _die('NO CATEGORIES EXISTS. Exiting.' . PHP_EOL);}$db->update(    $tables['categories'],     array('working' => 0),     array('working = ?' => 1, 'last_update < ?' => $db->quote(date('Y-m-d H:i:s', strtotime('-15min')))));$category_selected = false;foreach ($categories_items as $category_item){    $category_item = pq($category_item);    $category_data = getCategoryDataByName($category_item->text());    if (empty($category_data) || !empty($category_data['is_complete']) || !empty($category_data['working']))    {        continue;    }    $category_data['link'] = SITE_URL . prepareLink($category_item->attr('href'));    $category_selected = true;    break;}if (!$category_selected){    if ($next_letter)    {        _redirect(SCRIPT_ROOT_URL, array('letter' => $next_letter->text()));    }    _die('NO SELECTED CATEGORY. Exiting.' . PHP_EOL);}$db->update(    $tables['categories'],     array('last_update' => date('Y-m-d H:i:s'), 'working' => 1),     array('category_id = ?' => $category_data['category_id']));$category_link = $category_data['link'] . '/' . $category_data['cities_page_current'];unset($category_data['link']);if (!$category_page = getHtml($category_link)){    _redirect(SCRIPT_ROOT_URL, array('letter' => $letter_orig));    die(__FILE__ . ' : ' . __LINE__);}$category_page = phpQuery::newDocumentHTML($category_page, $charset);/** * Cities in a page */$cities_links = pq('.browseCityList a', $category_page);if (!$cities_links->count()){    _redirect(SCRIPT_ROOT_URL, array('letter' => $letter_orig));}/** * Updateing category data */if (empty($category_data['cities_page_total'])){    $pagination_total = max(1, (int) pq('.locations .cityPaginationLeft span:last', $category_page)->text());    $db->update(            $tables['categories'], array('cities_page_total' => $pagination_total), array('category_id = ?' => $category_data['category_id'])    );    $category_data['cities_page_total'] = $pagination_total;}/** * Looping throughout cities */foreach ($cities_links as $city_link){    /**     * Loading city data     */    $city_link = pq($city_link);    $city_data = array(        'name' => $city_link->text(),        'link' => prepareLink($city_link->attr('href'))    );    $city_data_db = getCityDataByName($city_data['name'], $category_data['category_id']);    if (!$city_data_db || empty($city_data_db['_rel_cc_']) || !empty($city_data_db['_rel_cc_']['is_complete']))    {        continue;    }    $city_data = array_merge($city_data, $city_data_db);    unset($city_data_db);    /**     * Loading city page with objects     */    $city_html_page = $city_data['_rel_cc_']['current_page'];    for (; $city_html_page < 1000; $city_html_page++)    {        $city_html_link = SITE_URL . $city_data['link'] . $city_html_page . '/?activeSort=sortname|asc';        $city_html = getHtml($city_html_link);        if (!$city_html)        {            if ($city_html_page > 1)            {                $db->update(                    $tables['rel_cc'],                     array('is_complete' => 1),                     array('category_id = ?' => $category_data['category_id'], 'city_id = ?' => $city_data['city_id'])                );            }            break;        }        $city_html = phpQuery::newDocumentHTML($city_html);        $city_total_items = (int) pq('#topBar .resstr strong:last')->text();        if ($city_total_items)        {            $db->update(                    $tables['rel_cc'], array('total_items' => $city_total_items), array(                'category_id = ? ' => $category_data['category_id'],                'city_id = ?' => $city_data['city_id']                    )            );        }        $vcards = pq('td.vcard', $city_html);        if (!$vcards->count())        {            $db->update(                    $tables['rel_cc'], array('is_complete' => 1), 'category_id = ' . $category_data['category_id'] . ' AND city_id = ' . $city_data['city_id']            );            break;        }        foreach ($vcards as $vcard)        {            $vcard = pq($vcard);            $vcard_data = array(                'object_id'   => $vcard->attr('id'),                'category_id' => $category_data['category_id'],                'city_id'     => $city_data['city_id'],                'name'        => trim($vcard->find('h2')->text()),                'address'     => $vcard->find('.adr')->text(),                'phone'       => $vcard->find('.tel')->text(),                'email'       => $vcard->find('.email a')->text(),                'website'     => $vcard->find('.url a')->attr('href')            );            $link = $vcard->find('h2 a')->attr('href');            $object_html = @getHtml(SITE_URL . $link);            if (!$object_html)            {                continue;            }            $object_html = phpQuery::newDocumentHTML($object_html);            $object_html = pq('.vcard', $object_html);            $vcard_data['area'] = $object_html->find('.extended-location')->text();            addVcard($vcard_data);            unset($vcard, $object_html, $vcard_data);        }        $db->update(            $tables['rel_cc'],             array('current_page' => $city_html_page + 1),             'category_id = ' . $category_data['category_id'] . ' AND city_id = ' . $city_data['city_id']        );    }    unset($city_html, $vcards);}$category_data['is_complete'] = (int) ($category_data['cities_page_current'] == $category_data['cities_page_total']);$category_data['working'] = 0;$category_data['last_update'] = date('Y-m-d H:i:s');if (!$category_data['is_complete']){    $category_data['cities_page_current']++;}$db->update(    $tables['categories'],     $category_data,     array('category_id = ?' => $category_data['category_id']));_redirect(SCRIPT_ROOT_URL, array('letter' => $letter_orig));die(__FILE__ . ' : ' . __LINE__);/** * Categories */$categories_html = getHtml(SITE_CATEGORIES_URL);$categories_html = phpQuery::newDocumentHTML($categories_html, $charset = 'utf-8');$categories_links = pq('#browseContainer dd a', $categories_html);if (!$categories_links->count()){    echo 'NO CATEGORIES. Exiting.';    die(__FILE__ . ' : ' . __LINE__);}/** * Loop throughout category links */foreach ($categories_links as $category_link){    /**     * Setting category data     */    $category_link = pq($category_link);    $category_data = array(        'name' => $category_link->text(),        'link' => prepareLink($category_link->attr('href'))    );    if (empty($category_data['name']))    {        continue;    }    $db_data = getCategoryDataByName($category_data['name']);    if (!$db_data)    {        continue;    }    $category_data = array_merge($category_data, $db_data);    unset($db_data);    if (!empty($category_data['is_complete']))    {        continue;    }    /**     * Fetching category page     */    $category_page = getHtml(SITE_URL . $category_data['link'] . '/' . $category_data['cities_page_current']);    if (!$category_page)    {        continue;    }    $category_page = phpQuery::newDocumentHTML($category_page, $charset = 'utf-8');    /**     * Updateing category data     */    if (empty($category_data['cities_page_total']))    {        $pagination_total = (int) pq('.locations .cityPaginationLeft span:last', $category_page)->text();        $db->update(                $tables['categories'], array('cities_page_total' => $pagination_total), array('category_id = ?' => $category_data['category_id'])        );        $category_data['cities_page_total'] = $pagination_total;    }    die(__FILE__ . ' : ' . __LINE__);    /**     * Looping throughout city pages      */    for ($i = $category_data['cities_page_current']; $i <= $category_data['cities_page_total']; $i++)    {        /**         * Updateing category         */        $db->update(                $tables['categories'], array('cities_page_current' => $i), 'category_id = ' . $category_data['category_id']        );        /**         * Loading new city page         */        if ($i != $current_city_page)        {            $category_page = getHtml(SITE_URL . $category_data['link'] . '/' . $i);            if (!$category_page)            {                continue;            }            $category_page = phpQuery::newDocumentHTML($category_page, $charset = 'utf-8');        }        /**         * Cities in a page         */        $cities_links = pq('.browseCityList a', $category_page);        if (!$cities_links->count())        {            continue;        }        /**         * Looping throughout cities         */        foreach ($cities_links as $city_link)        {            /**             * Loading city data             */            $city_link = pq($city_link);            $city_data = array(                'name' => $city_link->text(),                'link' => prepareLink($city_link->attr('href'))            );            $city_data_db = getCityDataByName($city_data['name'], $category_data['category_id']);            if (!$city_data_db || empty($city_data_db['_rel_cc_']) || !empty($city_data_db['_rel_cc_']['is_complete']))            {                continue;            }            $city_data = array_merge($city_data, $city_data_db);            unset($city_data_db);            /**             * Loading city page with objects             */            $city_html_page = $city_data['_rel_cc_']['current_page'];            for (; $city_html_page < 1000; $city_html_page++)            {                $city_html_link = SITE_URL . $city_data['link'] . $city_html_page . '/?activeSort=sortname|asc';                $city_html = @getHtml($city_html_link);                if (!$city_html)                {                    if ($city_html_page > 1)                    {                        $db->update(                                $tables['rel_cc'], array('is_complete' => 1), 'category_id = ' . $category_data['category_id'] . ' AND city_id = ' . $city_data['city_id']                        );                    }                    break;                }                $city_html = phpQuery::newDocumentHTML($city_html);                $city_total_items = (int) pq('#topBar .resstr strong:last')->text();                if ($city_total_items)                {                    $db->update(                            $tables['rel_cc'], array('total_items' => $city_total_items), array(                        'category_id = ? ' => $category_data['category_id'],                        'city_id = ?' => $city_data['city_id']                            )                    );                }                $vcards = pq('td.vcard', $city_html);                if (!$vcards->count())                {                    $db->update(                            $tables['rel_cc'], array('is_complete' => 1), 'category_id = ' . $category_data['category_id'] . ' AND city_id = ' . $city_data['city_id']                    );                    break;                }                foreach ($vcards as $vcard)                {                    $vcard = pq($vcard);                    $vcard_data = array(                        'object_id' => $vcard->attr('id'),                        'category_id' => $category_data['category_id'],                        'city_id' => $city_data['city_id'],                        'name' => trim($vcard->find('h2')->text()),                        'address' => $vcard->find('.adr')->text(),                        'phone' => $vcard->find('.tel')->text(),                        'email' => $vcard->find('.email a')->text(),                        'website' => $vcard->find('.url a')->attr('href')                    );                    $link = $vcard->find('h2 a')->attr('href');                    $object_html = @getHtml(SITE_URL . $link);                    if (!$object_html)                    {                        continue;                    }                    $object_html = phpQuery::newDocumentHTML($object_html);                    $object_html = pq('.vcard', $object_html);                    $vcard_data['area'] = $object_html->find('.extended-location')->text();                    addVcard($vcard_data);                    unset($vcard, $object_html, $vcard_data);                }                $db->update(                        $tables['rel_cc'], array('current_page' => $city_html_page + 1), 'category_id = ' . $category_data['category_id'] . ' AND city_id = ' . $city_data['city_id']                );            }            unset($city_html, $vcards);        }        if ($i == $category_data['cities_page_total'])        {            $db->update(                    $tables['categories'], array('is_complete' => 1), 'category_id = ' . $category_data['category_id']            );        }        unset($category_page, $cities_links);    }    unset($category_link, $category_page);}echo date('Y-m-d H:i:s') . PHP_EOL;die(__FILE__ . ' : ' . __LINE__);?>