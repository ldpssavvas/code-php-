<?php

/**
 * @author     Konstantinos A. Kogkalidis <konstantinos@tapanda.gr>
 * @copyright  2018 tapanda.gr <https://tapanda.gr/el/>
 * @license    Single website per license
 * @version    1.0
 * @since      1.0
 */

require_once _PS_MODULE_DIR_.'tp_classic_menu/classes/ClassicMenuCategory.php';

class tp_classic_menu extends Module
{
    public function __construct()
    {
		$this->name = 'tp_classic_menu';
		$this->tab = 'front_office_features';
		$this->version = '1.0';
		$this->author = 'tapanda.gr';
		$this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->controllers = array('products');
		$this->bootstrap = true;
        $this->module_dir = _PS_MODULE_DIR_.$this->name;

		parent::__construct();

		$this->displayName = $this->l('Μενού πιάτων');
		$this->description = $this->l('Παρουσιάζει συνοπτικά τα πιάτα που διαθέτε στο κατάστημα σας.');
		$this->confirmUninstall = $this->l('Είστε βέβαιος πως θέλετε να το απεγκαταστήσετε;');

        //Get the shop languages
        $this->languages = $this->getLanguages();        
	}

    /**
    *
    */
    //Get the installed languages (false: all, true: active)
    public function getLanguages($limit = false)
    {
        return Language::getLanguages($limit, $this->context->shop->id);
    }

	public function install()
    {
        if (!parent::install() or
            !$this->registerHook('displayClassicMenu') or
            !$this->registerHook('hookModuleRoutes'))
			return false;

        //Install tabs
        if(!$this->installTabs('AdminSkroutzSpy',$this->name,$this->displayName,null))
        {
            return false;
        }

        //Tables creation
        if(!$this->installTables($this->name))
        {
            return false;
        }

        return true;
    }

    /**
    *
    */
    public function uninstall()
    {
        if (!parent::uninstall())
            return false;

        //Install tabs
        if(!$this->uninstallTabs($this->name))
            return false;

        //Tables creation
        if(!$this->uninstallTables($this->name))
            return false;

        return true;
    }

    /**
    *
    */
    public function installTabs($class_name,$name,$displayName,$faketab)
    {
        if($faketab != null)
        {
            //Fake tab creation to assign customers with various types
            $tab = new Tab();
            $tab->class_name = $faketab->class_name;
            $tab->module = $name;
            $tab->id_parent = -1;
            foreach($this->languages as $l){
                $tab->name[$l['id_lang']] = $faketab->displayName;
            }
            $tab->save();
        }

        //Parent tab creation
        $tab = new Tab();
        $tab->class_name = $class_name;
        $tab->module = $name;
        $tab->id_parent = 0;
        foreach($this->languages as $l)
        {
            $tab->name[$l['id_lang']] = $displayName;
        }
        $tab->save();

        //Sub-tabs creation
        $tab_id = $tab->id;
        require_once _PS_MODULE_DIR_.$name.'/sql/install_tabs.php';
        foreach ($tabvalue as $tab)
        {
            $newtab = new Tab();
            $newtab->class_name = $tab['class_name'];
            $newtab->id_parent = $tab_id;
            $newtab->module = $tab['module'];
            foreach ($this->languages as $l){
                $newtab->name[$l['id_lang']] = $this->l($tab['name']);
            }
            $newtab->save();
        }

        return true;
    }

    /**
    *
    */
    public function uninstallTabs($name)
    {
        $sql = array();
        require_once _PS_MODULE_DIR_.$name.'/sql/uninstall_tabs.php';
        foreach ($sql as $s)
        {
            if($s)
            {
                $tab = new Tab($s);
                $tab->delete();
            }
        }

        return true;
    }

    /**
    *
    */
    public function installTables($name)
    {
        $sql = array();
        require_once _PS_MODULE_DIR_.$name.'/sql/install.php';
        foreach ($sql as $s)
        {
            if (!Db::getInstance()->Execute($s))
                return false;
        }

        return true;
    }

    /**
    *
    */
    public function uninstallTables($name)
    {
        $sql = array();
        require_once _PS_MODULE_DIR_.$name.'/sql/uninstall.php';
        foreach ($sql as $s)
        {
            if (!Db::getInstance()->Execute($s))
                return false;
        }

        return true;
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path.'views/css/bo.css');
        $this->context->controller->addJS('localhost/konstantinos/js/jquery/jquery-1.11.0.min.js');
        $this->context->controller->addJS($this->_path.'views/js/bo.js');
    }

    /**
    * General select query
    *
    * @param $select varchar We select all (*) or we restrict the data we are going to get back
    *
    * @param $from varchar The respective table that we will extract the data from
    * In complicated situations, we may join tables using this variable
    *
    * @param $where varchar If set, it narrows down the search given specific criteria
    *
    * @param $order_by varchar If set, it rearranges the display order
    *
    * @param $limit int If set, it makes the query return only the first X rows that match the criteria
    *
    * @param $offset int Useful for pagination, makes the query ignore the first Y rows
    *
    * @return Returns an array with rows that match the criteria that have been set
    */
    public static function selectQuery($from,$where = null,$order_by = null,$limit = null,$offset = null,$select = '*')
    {
        if($where === null)
            $where = '';
        else
            $where = ' WHERE '.$where;

        if($order_by === null)
            $order_by = '';
        else
            $order_by = ' ORDER BY '.$order_by;

        if($limit === null)
            $limit = '';
        else
            $limit = ' LIMIT '.$limit;

        if($offset === null)
            $offset = '';
        else
            $offset = ' OFFSET '.$offset;

        $sql = 'SELECT '.$select.' FROM `'._DB_PREFIX_.$from.$where.$order_by.$limit.$offset;

        $result = (db::getInstance())->executeS($sql);

        return $result;
    }

    /**
    * It returns lang included queries
    *
    * @param 
    */
    public function selectQueryLang($table,$lid,$restriction = null,$order_by = null,$limit = null, $offset = null,$select = '*')
    {
        //Debug
        $sql = $this->selectQuery($table.'` t LEFT JOIN `'._DB_PREFIX_.$table.'_lang` tl ON tl.`id_'.$table.'` = t.`id_'.$table.'`','`id_lang` = '.$lid.$restriction,$order_by,$limit,$offset,$select);

        //$sql = $this->selectQuery($select,$table.'` t LEFT JOIN `'._DB_PREFIX_.$table.'_lang` tl ON tl.`id_'.$table.'` = t.`id_'.$table.'`','`status` = 1 AND `id_lang` = '.$lid.$restriction,$order_by);

        return $sql;
    }

    /**
    * Αυτή η συνάρτηση επιστρέφει τα προϊόντα που υπάρχουν τόσο στην Skroutz όσο και στον ιστότοπο του πελάτη μας
    */
    public function getProducts($cid,$lid)
    {
        //We get the products total that are assigned to the specified category
        $products = $this->selectQuery('category_product`','`id_category` = '.$cid);

        $fields = [];
        $fields[0] = 'id_product';

        //We convert the product IDs to csv
        $in = $this->tableToCSV($products,$fields);

        $sql = $this->selectQueryLang('product',$lid,' AND t.`id_product` IN '.$in,'t.`id_product` DESC',6);

        //We add the cover id
        $result = [];

        for ($x=0; $x < count($sql); $x++)
        { 
            $result[$x] = $sql[$x];
            $result[$x]['image'] = $this->getValue('image','id_image',null,'`id_product` = '.$sql[$x]['id_product'].' AND `cover` = 1');

            $product = new Product($sql[$x]['id_product']);

            $result[$x]['link'] = Context::getContext()->link->getProductLink($product);
            $result[$x]['price'] = number_format((float)($sql[$x]['price'] * 1.24),2,',','');
        }

        return $result;
    }

    /**
    * This function returns the products that exist in the Skroutz products XML feed
    */
    //public function getSkroutzProducts($products,$lid,$limit = 0)

    /**

    */
    public function getValue($table,$column,$order_by = null,$where = null)
    {
        if($where === null)
            $where = '';
        else
            $where = ' WHERE '.$where;

        if($order_by === null)
            $order_by = '';
        else
            $order_by = ' ORDER BY '.$order_by;

        $sql = 'SELECT DISTINCT `'.$column.'` FROM `'._DB_PREFIX_.$table.'`'.$where.$order_by;

        //Debug
        //echo (db::getInstance())->getValue($sql);

        return (db::getInstance())->getValue($sql);
    }

    /**

    */
    public function getCombination($ipa,$lid)
    {
        $result = '';

        //Ανάκτηση των attributes του συνδυασμού
        $sql = $this->selectQuery('product_attribute_combination`','`id_product_attribute` = '.$ipa);

        foreach($sql as $s)
        {
            $id = $this->getValue('attribute','id_attribute',null,'`id_attribute` = '.$s['id_attribute']);

            //Ανάκτηση του attribute
            $attribute = new Attribute($id,$lid);

            $group = new AttributeGroup($attribute->id_attribute_group,$lid);

            $result .= ' - '.$group->name.': '.$attribute->name;
        }

        $result = ltrim($result,' - ');

        return $result;
    }

    /**

    */
    public function getCategoriesTree($language)
    {
        //We get the categories
        $sql = $this->selectQueryLang('category',$language,' AND t.`id_category` > 1 AND `active` = 1','`level_depth` ASC,`nleft` ASC');

        //We have to add `position` column to the results
        $categories = $this->getModifiedCategories($sql);

        $result = $this->makeCategoriesTree($categories,$language);

        //$this->makeCategoriesTree($categories,$language);

        return $result;
    }

    /**

    */
    public function getModifiedCategories($categories)
    {
        $result = [];

        $result[0] = $categories[0];
        $result[0]['position'] = 0;

        for ($x=1; $x < count($categories); $x++)
        {
            $y = $x - 1;

            $result[$x] = $categories[$x];

            if($result[$x]['level_depth'] == $result[$y]['level_depth'] AND $result[$x]['id_parent'] == $result[$y]['id_parent'])
            {
                $result[$x]['position'] = $result[$y]['position'] + 1;
            }else
            {
                $result[$x]['position'] = 0;
            }
        }

        return $result;
    }

    public function makeCategoriesTree($sql,$language)
    {
        //Table 
        $table = 'category';

        $result = [];

        for ($x=0; $x < count($sql); $x++)
        {
            $result[$x] = $sql[$x];

            //Get the max level of categories depth
            $max_level = $this->getValue($table,'level_depth','`level_depth` desc');

            //Get the parents of the specific category
            $parents = $this->getParents($table,$result[$x],$max_level);

            //Put them in the table
            for ($p=0; $p < count($parents); $p++)
            {
                $result[$x]['par'.$p] = $parents[$p];
            }
        }

        //We put it into separate for, because we need the outcome of the previous one
        for ($x=0; $x < count($sql); $x++)
        {
            $category = new Category($result[$x]['id_category'],$language);

            $result[$x]['children_total'] = $category->getAllChildren($language);
        }

        //Update the actual positions with the absolute ones
        $result = $this->updatePositions($table,$result);

        //We sort the results based on the `pos` field
        $result = $this->bubbleSort($result,'tree_position');

        return $result;
    }

    /**

    */
    public function updatePositions($table,$result)
    {
        $result[0]['tree_position'] = 0;

        for ($x=1; $x < count($result); $x++)
        {
            $y = $x - 1;

            while($result[$x]['id_parent'] != $result[$y]['id_'.$table] && $result[$x]['id_parent'] != $result[$y]['id_parent'])
                $y--;

            if($result[$x]['id_parent'] == $result[$y]['id_'.$table])
                $result[$x]['tree_position'] = $result[$y]['tree_position'] + 1;
            else
                $result[$x]['tree_position'] = $result[$y]['tree_position'] + count($result[$y]['children_total']) + 1;
        }

        return $result;
    }

    /**

    */
    public static function bubbleSort($result,$field = 'position')
    {
        for($x = 0;$x < count($result) - 1;$x++)
        {
            for($y = count($result) - 1;$y > $x;$y--)
            {
                if($result[$x][$field] > $result[$y][$field])
                {
                    $temp = $result[$x];
                    $result[$x] = $result[$y];
                    $result[$y] = $temp;
                }
            }
        }

        return $result;
    }

    /**
    * @param
    */
    public function getParents($table,$child,$max_level)
    {
        $result = [];

        $result[0] = $child['id_parent'];

        for ($x=1; $x < $max_level - 1; $x++)
        {
            $y = $x - 1;

            if($result[$y] == 0)
                $result[$y] = 0;
            else
                $result[$x] = $this->getParent($table,$result[$y]);
        }

        return $result;
    }

    /**

    */
    public function getParent($table,$row)
    {
        return $this->getValue($table,'id_parent','`id_'.$table.'` = '.$row);
    }

    /**

    */
    public function getAdminLink($controller,$type = null,$table = null,$value = null)
    {
        $extra = '';

        if($type !== null)
        {
            if($type == '0')
                $extra .= '&action=add';
            elseif($type == '1')
                $extra .= '&action=edit';
            elseif($type == 'ajaxprocessgalleriesview')
                $extra .= '&action=ajaxprocessgalleriesview';
            elseif($type == 'ajaxprocessheaderupdate')
                $extra .= '&action=ajaxprocessheaderupdate';
            elseif($type == 'ajaxprocessupdate')
                $extra .= '&action=ajaxprocessupdate';
            elseif($type == 'ajaxprocessplaces')
                $extra .= '&action=ajaxprocessplaces';
            elseif($type == 'ajaxprocesscategoryview')
                $extra .= '&action=ajaxprocesscategoryview';
            else
                $extra .= '&action=view';
        }//else
            //$extra .= '&action=ajaxprocessform';

        return Context::getContext()->link->getAdminLink('Admin'.$controller).$extra;
    }

    /**
    * This tiny function calculates the items that we will ignore (eg. in a products, posts etc listing)
    */
    public function calculateOffset($page,$items)
    {
        $result = ($page - 1) * $items;
        return $result;
    }

    /**
    *
    */
    public function listToCSV($list)
    {
        $in = '(';
        foreach($list as $row)
        {
            $in .= $row.',';
        }

        $in = rtrim($in,',');

        //Parenthesis add
        $in .= ')';
        return $in;
    }

    /**
    *
    */
    public function tableToCSV($sql,$fields)
    {
        $i = 0;

        $values = '(';

        foreach($sql as $row)
        {
            foreach($fields as $col)
            {
                $values .= '"'.$row[$col].'",';
            }

            //Last comma deletion
            $values = rtrim($values,',');

            //Comma add
            $values .= ',';
        }

        //Last comma deletion
        $values = rtrim($values,',');

        //Parenthesis add
        $values .= ')';

        return $values;
    }

    /**
    *
    */
    public function listOrForm($link)
    {
        if(strpos($link,'tp_classic_menu_category') != null && strpos($link,'conf') == null)
            return 1;
        else
            return 0;
    }

    public function getCategoriesWithMenuIDs($sql)
    {
        $result = [];

        for ($x=0; $x < count($sql); $x++)
        { 
            $result[$x] = $sql[$x];
            $result[$x]['menu_id'] = $this->getValue('tp_classic_menu_category','id_tp_classic_menu_category',null,'`category_id` = '.$sql[$x]['id_category']);
        }

        return $result;
    }

    public function getCategories($language)
    {
        $sql = $this->selectQuery('tp_classic_menu_category`',null,'`position` ASC');

        $sql = $this->makeCategories($sql,$language);

        //$result = $this->getCategoriesWithLinks($sql);

        return $sql;
    }

    public function makeCategories($sql,$language)
    {
        $result = [];

        for ($x=0; $x < count($sql); $x++)
        {
            $category = new Category($sql[$x]['category_id'],$language);
            $result[$x] = $category;
        }

        return $result;
    }

    public function hookDisplayClassicMenu()
    {
        $categories = $this->getCategories($this->context->language->id);

        $pure_link = 'module/tp_classic_menu/products';

        $first_category = $this->getValue('tp_classic_menu_category','category_id','`position` ASC');

        $this->context->smarty->assign(array(
            'pure_link' => $pure_link,
            'categories' => $categories,
            'first_category' => $first_category
        ));

        return $this->display(__FILE__,'views/templates/hook/classic_menu.tpl');
    }

    public function hookModuleRoutes($params)
    {
        return array(
            //Ajax controller to get the products
            'module-tp_classic_menu-products' => array(
                'controller' => 'products',
                'rule' => 'paparies',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => 'tp_classic_menu'
                )
            )
        );
    }
}
