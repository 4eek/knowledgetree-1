<?php

//require_once('../../config/dmsDefaults.php');

/**
 * This is the ideal case, but more complex
 *
 */

require_once('indexing/indexerCore.inc.php');
require_once('search/fieldRegistry.inc.php');
require_once('search/exprConstants.inc.php');

class RankManager
{
	/**
	 * This array contains the rankings of fields on database tables.
	 *
	 * @var array
	 */
	private $db;
	/**
	 * Contains the rankings of metadata fields on fieldset/field combinations.
	 *
	 * @var array
	 */
	private $metadata;
	/**
	 * Contains ranking factor for discussion matching
	 *
	 * @var float
	 */
	private $discussion;
	/**
	 * Contains the ranking factor for text matching
	 *
	 * @var float
	 */
	private $text;

	private function __construct()
	{
		$this->dbfields=array();
		$sql = "SELECT groupname, itemname, ranking, type FROM search_ranking";
		$rs = DBUtil::getResultArray($sql);
		foreach($rs as $item)
		{
			switch ($item['type'])
			{
				case 'T':
					$this->db[$item['groupname']][$item['itemname']] = $item['ranking']+0;
					break;
				case 'M':
					$this->metadata[$item['groupname']][$item['itemname']] = $item['ranking']+0;
					break;
				case 'S':
					switch($item['groupname'])
					{
						case 'Discussion':
							$this->discussion = $item['ranking']+0;
							break;
						case 'DocumentText':
							$this->text = $item['ranking']+0;
							break;
					}
					break;
			}
		}
	}

	/**
	 * Enter description here...
	 *
	 * @return RankManager
	 */
	public static function get()
	{
		static $singleton = null;
		if (is_null($singleton))
		{
			$singleton = new RankManager();
		}
		return $singleton;
	}

	public function scoreField($groupname, $type='T', $itemname='')
	{
		switch($type)
		{
			case 'T':
				return $this->db[$groupname][$itemname];
			case 'M':
				return $this->metadata[$groupname][$itemname];
			case 'S':
				switch($groupname)
				{
					case 'Discussion':
						return $this->discussion;
					case 'DocumentText':
						return $this->text;
					default:
						return 0;
				}
			default:
				return 0;
		}
	}
}


class Expr
{
    /**
     * The parent expression
     *
     * @var Expr
     */
    protected $parent;

    protected static $node_id = 0;

    protected $expr_id;

    public function __construct()
    {
        $this->expr_id = Expr::$node_id++;
    }

    public function getExprId()
    {
        return $this->expr_id;
    }

    /**
     * Coverts the expression to a string
     *
     * @return string
     */
    public function __toString()
    {
        throw new Exception('Not yet implemented in ' . get_class($this));
    }

    /**
     * Reference to the parent expression
     *
     * @return Expr
     */
    public function &getParent()
    {
        return $this->parent;
    }

    /**
     * Sets the parent expiression
     *
     * @param Expr $parent
     */
    public function setParent(&$parent)
    {
        $this->parent = &$parent;
    }

    /**
     * Is the expression valid
     *
     * @return boolean
     */
    public function is_valid()
    {
        return true;
    }

    public function isExpr()
    {
    	return $this instanceof OpExpr;
    }

    public function isOpExpr()
    {
    	return $this instanceof OpExpr;
    }
    public function isValueExpr()
    {
    	return $this instanceof ValueExpr;
    }
    public function isValueListExpr()
    {
    	return $this instanceof ValueListExpr;
    }

    public function isDbExpr()
    {
    	return $this instanceof DBFieldExpr;
    }

    public function isFieldExpr()
    {
    	return $this instanceof FieldExpr;
    }

    public function isSearchableText()
    {
    	return $this instanceof SearchableText ;
    }

    public function isMetadataField()
    {
    	return $this instanceof MetadataField;
    }





    public function toViz(&$str, $phase)
    {
        throw new Exception('To be implemented' . get_class($this));
    }

    public function toVizGraph($options=array())
    {
        $str = "digraph tree {\n";
        if (isset($options['left-to-right']) && $options['left-to-right'])
        {
            $str .= "rankdir=LR\n";
        }

        $this->toViz($str, 0);
        $this->toViz($str, 1);

        $str .= "}\n";

        if (isset($options['tofile']))
        {
            $path=dirname($options['tofile']);
            $filename=basename($options['tofile']);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $base = substr($filename, 0, -strlen($ext)-1);

            $dotfile="$path/$base.$ext";
            $jpgfile="$path/$base.jpg";
            $fp = fopen($dotfile,'wt');
            fwrite($fp, $str);
            fclose($fp);

            system("dot -Tjpg -o$jpgfile $dotfile");

            if (isset($options['view']) && $options['view'])
            {
                system("eog $jpgfile");
            }
        }

        return $str;
    }
}

class FieldExpr extends Expr
{
    /**
     * Name of the field
     *
     * @var string
     */
    protected $field;

    protected $alias;

    protected $display;


    /**
     * Constructor for the field expression
     *
     * @param string $field
     */
    public function __construct($field, $display=null)
    {
        parent::__construct();
        $this->field=$field;
        if (is_null($display))
        {
        	$display=get_class($this);
        }
        $this->display = $display;
        $this->setAlias(get_class($this));
    }

    public function setAlias($alias)
    {
        $this->alias=$alias;
    }

    public function getDisplay()
    {
    	return $this->display;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getFullName()
    {
        return $this->alias . '.' . $this->field;
    }

    /**
     * Returns the field
     *
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Coverts the expression to a string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->alias;
    }

    public function toViz(&$str, $phase)
    {
        if ($phase == 0)
        {
            $expr_id = $this->getExprId();
            $str .= "struct$expr_id [style=rounded, label=\"$expr_id: FIELD[$this->alias]\"]\n";
        }
    }

    public function rewrite(&$left, &$op, &$right, $not=false)
    {
    	$input = $left->getInputRequirements();

		if ($input['value']['type'] != FieldInputType::FULLTEXT)
		{
			return;
		}


    	if ($right->isValueExpr())
		{
			$value = $right->getValue();
		}
		else
		{
			$value = $right;
		}

		if (substr($value,0,1) != '\'' || substr($value,-1) != '\'')
		{
			OpExpr::rewriteString($left, $op, $right, $not);
		}
		else
		{
			$right = new ValueExpr(trim(substr($value,1,-1)));
		}
    }
}

class DBFieldExpr extends FieldExpr
{
    /**
     * The table the field is associated with
     *
     * @var string
     */
    protected $table;

    protected $jointable;
    protected $joinfield;
    protected $matchfield;
    protected $quotedvalue;


    /**
     * Constructor for the database field
     *
     * @param string $field
     * @param string $table
     */
    public function __construct($field, $table, $display=null)
    {
    	if (is_null($display))
    	{
    		$display = get_class($this);
    	}

        parent::__construct($field, $display);

        $this->table=$table;
        $this->jointable = null;
        $this->joinfield = null;
        $this->matchfield = null;
        $this->quotedvalue=true;
    }

    /**
     * Returns the table name
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    public function joinTo($table, $field)
    {
    	$this->jointable=$table;
    	$this->joinfield=$field;
    }
    public function matchField($field)
    {
    	$this->matchfield = $field;
    }

	public function modifyName($name)
    {
    	return $name;
    }

	public function modifyValue($value)
    {
    	return $value;
    }


    public function getJoinTable() { return $this->jointable; }
    public function getJoinField() { return $this->joinfield; }
    public function getMatchingField() { return $this->matchfield; }
    public function isValueQuoted($quotedvalue = null)
    {
    	if (isset($quotedvalue))
    	{
    		$this->quotedvalue = $quotedvalue;
    	}
    	return $this->quotedvalue;
    }
}

class MetadataField extends DBFieldExpr
{
    protected $fieldset;
    protected $fieldid;
    protected $fieldsetid;

    public function __construct($fieldset, $field, $fieldsetid, $fieldid)
    {
        parent::__construct($field, 'document_fields_link');
        $this->fieldset=$fieldset;
        $this->fieldid=$fieldid;
        $this->fieldsetid=$fieldsetid;
    }

    public function getFieldSet()
    {
        return $this->fieldset;
    }

    public function getFieldId()
    {
        return $this->fieldid;
    }

    public function getFieldSetId()
    {
        return $this->fieldsetid;
    }

    public function getInputRequirements()
    {
        return array('value'=>array('type'=>FieldInputType::TEXT));
    }

    /**
     * Coverts the expression to a string
     *
     * @return string
     */
    public function __toString()
    {
        return "METADATA[$this->fieldset][$this->field]";
    }

}

class SearchableText extends FieldExpr
{
}

class ValueExpr extends Expr
{
    /**
     * The value
     *
     * @var mixed
     */
    protected $value;

    /**
     * Constructor for the value expression
     *
     * @param mixed $value
     */
    public function __construct($value)
    {
        parent::__construct();
        $this->value=$value;
    }

    public function getValue()
    {
        return $this->value;
    }

    /**
     * Converts the value to a string
     *
     * @return unknown
     */
    public function __toString()
    {
        return (string) "\"$this->value\"";
    }

    public function toViz(&$str, $phase)
    {
        if ($phase == 0)
        {
            $expr_id = $this->getExprId();
            $value = addslashes($this->value);
            $str .= "struct$expr_id [style=ellipse, label=\"$expr_id: \\\"$value\\\"\"]\n";
        }
    }

	public function getSQL($field, $fieldname, $op, $not=false)
    {
    	$val = $field->modifyValue($this->getValue());
    	$quote = '';
    	if ($field->isValueQuoted())
    	{
    		$val = addslashes($val);
    		$quote = '\'';
    	}

        switch($op)
        {
            case ExprOp::CONTAINS:
                $sql = "$fieldname LIKE '%$val%'";
                break;
            case ExprOp::STARTS_WITH:
                $sql = "$fieldname LIKE '$val%'";
                break;
            case ExprOp::ENDS_WITH:
                $sql = "$fieldname LIKE '%$val'";
                break;
            case ExprOp::IS:
                $sql = "$fieldname = $quote$val$quote";
                break;
            case ExprOp::GREATER_THAN :
                $sql = "$fieldname > $quote$val$quote";
                break;
            case ExprOp::GREATER_THAN_EQUAL  :
                $sql = "$fieldname >= $quote$val$quote";
                break;
            case ExprOp::LESS_THAN  :
                $sql = "$fieldname < $quote$val$quote";
                break;
            case ExprOp::LESS_THAN_EQUAL :
                $sql = "$fieldname <= $quote$val$quote";
                break;
            default:
                throw new Exception('Unknown op: ' . $op);
        }

        if ($not)
        {
            $sql = "not ($sql)";
        }

        return $sql;
    }

}

class ValueListExpr extends Expr
{
    /**
     * The value
     *
     * @var mixed
     */
    protected $values;

    /**
     * Constructor for the value expression
     *
     * @param mixed $value
     */
    public function __construct($value)
    {
        parent::__construct($value);
        $this->values=array($value);
    }

    public function addValue($value)
    {
    	$this->values[] = $value;
    }


    public function getValue($param=null)
    {
    	if (!empty($param))
    	{
    		return $this->values[$param];
    	}
        $str = '';

        foreach($this->values as $value)
        {
        	if ($str != '') $str .= ',';
        	$str .= "\"$value\"";
        }

        return $str;
    }

    /**
     * Converts the value to a string
     *
     * @return unknown
     */
    public function __toString()
    {
        return $this->getValue();
    }

    public function toViz(&$str, $phase)
    {
        if ($phase == 0)
        {
            $expr_id = $this->getExprId();

            $str .= "struct$expr_id [style=ellipse, label=\"$expr_id: ";
            $i=0;
            foreach($this->values as $value)
            {
            	if ($i++>0) $str .= ',';
            	$value = addslashes($value);
            	$str .= "\\\"$value\\\"";
            }
            $str .= "\"]\n";
        }
    }



    public function rewrite(&$left, &$op, &$right, &$not)
    {
    	if (count($this->values) == 1)
		{
			$right = new ValueExpr($this->values[0]);
			return;
		}
		$newops = array();
		foreach($this->values as $value)
		{
			$classname = get_class($left);
			$class = new $classname;
			$newop = new OpExpr($class, $op, $value);
			$newops[] = $newop;
		}

		$result = $newops[0];
		for($i=1;$i<count($newops);$i++)
		{
			$result = new OpExpr($result, ExprOp::OP_OR, $newops[$i]);
		}

		$left = $result->left();
		$op = $result->op();
		$right = $result->right();
    }

}


class BetweenValueExpr extends ValueExpr
{
    protected $endvalue;

    public function __construct($start, $end)
    {
        parent::__construct($start);
        $this->endvalue = $end;
    }

    public function getStart()
    {
        return $this->getValue();
    }

    public function getEnd()
    {
        return $this->endvalue;
    }

    /**
     * Converts the value to a string
     *
     * @return unknown
     */
    public function __toString()
    {
        return (string) $this->value  . ' AND ' . $this->endvalue;
    }

    public function toViz(&$str, $phase)
    {
        if ($phase == 0)
        {
            $value = addslashes($this->value);
            $value2 = addslashes($this->endvalue);

            $expr_id = $this->getExprId();
            $str .= "struct$expr_id [style=rounded, label=\"$expr_id: $value AND $value2\"]\n";
        }
    }

    public function getSQL($field, $fieldname, $op, $not=false)
    {
        if ($op != ExprOp::BETWEEN)
        {
            throw new Exception('Unexpected operator: ' . $op);
        }

		$quote = '';

		$start = $field->modifyValue($this->getStart());
		$end = $field->modifyValue($this->getEnd());

		if ($field->isValueQuoted())
    	{
    		$start = addslashes($start);
    		$end = addslashes($end);
    		$quote = '\'';
    	}


        $not = $not?' NOT ':'';
        return "$not ($fieldname $op $quote$start$quote AND $quote$end$quote) ";
    }
}

interface QueryBuilder
{
	function buildComplexQuery($expr);

	function buildSimpleQuery($op, $group);

	function getRanking($result);

	function getResultText($result);

}

class TextQueryBuilder implements QueryBuilder
{
	private $text;
	private $query;

	public function buildComplexQuery($expr)
	{
		$left = $expr->left();
        $right = $expr->right();
		if (DefaultOpCollection::isBoolean($expr))
		{
			$query = '(' . $this->buildComplexQuery($left) . ' ' . $expr->op() . ' ' . $this->buildComplexQuery($right)  . ')';

			if ($expr->not())
			{
				$query = "NOT $query";
			}
		}
		else
		{
			$fieldname = $left->getField();
            $value = addslashes($right->getValue());

            $not = $expr->not()?' NOT ':'';

			$query = "$not$fieldname: \"$value\"";
		}

		return $query;
	}

	public function buildSimpleQuery($op, $group)
	{
		$query = '';
		foreach($group as $expr)
		{
			if (!empty($query))
			{
				$query .= " $op ";
			}

			$left = $expr->left();
            $right = $expr->right();

			$fieldname = $left->getField();
            $value = addslashes($right->getValue());

            $not = $expr->not()?' NOT ':'';

			$query .= "$not$fieldname: \"$value\"";
		}

		return $query;
	}

	public function getRanking($result)
	{
		$init = $result->Rank;
		$score=0;
		$ranker = RankManager::get();
		$discussion = $result->Discussion;
		if (!empty($discussion))
		{
			$score += $init *$ranker->scoreField('Discussion', 'S');
		}
		else
		{
			$score += $init *$ranker->scoreField('DocumentText', 'S');

		}
		return $score;
	}

	public function setQuery($query)
	{
		$this->query = $query;
	}

	private function extractText($word, $maxwords=40, $maxlen=512)
	{
		$offset=stripos($this->text, $word);

		if ($offset == false)
		{
			return array(false, false);
		}

		$text = substr($this->text, 0 , $offset);

		$lastsentence = strrpos($text, '.');
		if (!$lastsentence) $lastsentence=0;

		if ($offset - $lastsentence  >  $maxlen)
		{
			$lastsentence = $offset - $maxlen;
		}

		$text = substr($this->text, $lastsentence, $offset - $lastsentence);

		$wordoffset= strlen($text)-1;
		$words = $maxwords;
		while ($words > 0)
		{
			$text = substr($text, 0, $wordoffset);
			$foundoffset = strrpos($text, ' ');
			if ($foundoffset === false)
			{
				break;
			}
			$wordoffset = $foundoffset;
			$words--;
		}

		$startOffset = $lastsentence + $wordoffset;

		$nextsentence = strpos($this->text, '.', $offset);

		$words = $maxwords;
		$endOffset = $offset;
		while ($words > 0)
		{
				$foundoffset = strpos($this->text, ' ', $endOffset+1);
				if ($foundoffset === false)
				{
					break;
				}
				if ($endOffset > $offset + $maxlen)
				{
					break;
				}
				if ($endOffset > $nextsentence)
				{
					$endOffset = $nextsentence-1;
					break;
				}
				$endOffset = $foundoffset;

				$words--;
		}

		return array($startOffset, substr($this->text, $startOffset, $endOffset - $startOffset + 1));
	}


	public function getResultText($result)
	{
		$this->text = substr($result->Text,0,40960);
		$words = array();
		$sentences = array();

		preg_match_all('("[^"]*")',$this->query, $matches,PREG_OFFSET_CAPTURE);

		foreach($matches[0] as $word)
		{
			list($word,$offset) = $word;
			$word = substr($word,1,-1);
			$wordlen = strlen($word);
			$res = $this->extractText($word);
			list($sentenceOffset,$sentence) = $res;

			if ($sentenceOffset === false)
			{
				continue;
			}

			if (array_key_exists($sentenceOffset, $sentences))
			{
				$sentences[$sentenceOffset]['score']++;
			}
			else
			{
				$sentences[$sentenceOffset] = array(
					'sentence'=>$sentence,
					'score'=>1
				);
			}

			$sentence = $sentences[$sentenceOffset]['sentence'];

			preg_match_all("@$word@i",$sentence, $swords,PREG_OFFSET_CAPTURE);
			foreach($swords[0] as $wordx)
			{
				list($wordx,$offset) = $wordx;

				$sentence = substr($sentence,0, $offset) . '<b>' . substr($sentence, $offset, $wordlen) . '</b>' . substr($sentence, $offset + $wordlen);
			}

			$sentences[$sentenceOffset]['sentence']	= $sentence;

			$words[$word] = array(
				'sentence'=>$sentenceOffset
			);
		}

		ksort($sentences);
		$result = '';

		foreach($sentences as $o=>$i)
		{
			if (!empty($result)) $result .= '&nbsp;&nbsp;&nbsp;...&nbsp;&nbsp;&nbsp;&nbsp;';
			$result .= $i['sentence'];
		}

		return $result;
	}

}

class SQLQueryBuilder implements QueryBuilder
{
	private $used_tables;
	private $aliases;
	private $sql;
	private $db;
	private $metadata;

	public function __construct()
	{
		$this->used_tables = array(
            'documents'=>1,
            'document_metadata_version'=>1,
            'document_content_version'=>0,
            'tag_words'=>0,
            'document_fields_link'=>0
        );

        $this->aliases = array(
                    'documents'=>'d',
                    'document_metadata_version'=>'dmv',
                    'document_content_version'=>'dcv',
                    'tag_words'=>'tw',
                    'document_fields_link'=>'pdfl'
                    );

		$this->sql = '';
		$this->db = array();
		$this->metadata = array();
	}

	/**
	 * This looks up a table name to find the appropriate alias.
	 *
	 * @param string $tablename
	 * @return string
	 */
	private function resolveTableToAlias($tablename)
	{
		if (array_key_exists($tablename, $this->aliases))
		{
			return $this->aliases[$tablename];
		}
		throw new Exception("Unknown tablename '$tablename'");
	}

	private function exploreExprs($expr, $parent=null)
	{
		if ($expr->isMetadataField())
		{
			$this->metadata[] = & $parent;
		}
		elseif ($expr->isDBExpr())
		{
			$this->db[]  = & $parent;
			$this->used_tables[$expr->getTable()]++;
		}
		elseif ($expr->isOpExpr())
		{
			$left = & $expr->left();
			$right = & $expr->right();
			if (DefaultOpCollection::isBoolean($expr))
			{
				$this->exploreExprs($left, $expr);
				$this->exploreExprs($right, $expr);
			}
			else
			{
				// if it is not a boolean, we only need to explore left as it is the one where the main field is defined.
				$this->exploreExprs($left, $expr);
			}
		}
	}

	private function exploreGroup($group)
	{
		// split up metadata and determine table usage
        foreach($group as $expr)
        {
            $field = $expr->left();

            if ($field->isMetadataField())
            {
            	$this->metadata[] = $expr->getParent();
            }
            elseif ($field->isDBExpr())
            {
            	$this->db[]  = $expr->getParent();
            	$this->used_tables[$field->getTable()]++;
            }
        }
	}

	private function getFieldnameFromExpr($expr)
	{
		$field = $expr->left();
		if (is_null($field->getJoinTable()))
		{
			$alias      = $this->resolveTableToAlias($field->getTable());
			$fieldname  = $alias . '.' . $field->getField();
		}
		else
		{
			$offset = $this->resolveJoinOffset($expr);
			$matching = $field->getMatchingField();
			$tablename = $field->getJoinTable();
			$fieldname = "$tablename$offset.$matching";
		}

		return $fieldname;
	}

	private function getSQLEvalExpr($expr)
	{
		$left = $expr->left();
		$right = $expr->right();
		if ($left->isMetadataField())
		{
			$offset = $this->resolveMetadataOffset($expr) + 1;

			$fieldset = $left->getField();
			$query = '(' . "df$offset.name='$fieldset' AND " .  $right->getSQL($left, "dfl$offset.value", $expr->op(), false) . ')';

		}
		else
		{
			$fieldname = $this->getFieldnameFromExpr($expr);

			$query = $right->getSQL($left, $left->modifyName($fieldname), $expr->op(), $expr->not());;
		}
		return $query;
	}

	private function buildCoreSQL()
	{
		if (count($this->metadata) + count($this->db) == 0)
        {
            throw new Exception('nothing to do');
        }

        // we are doing this because content table is dependant on metadata table
        if ($this->used_tables['document_content_version'] > 0)  $this->used_tables['document_metadata_version']++;

		$sql =
            'SELECT ' . "\n";

        $sql .=
            ' DISTINCT d.id, dmv.name as title';

        $offset=0;
        foreach($this->db as $expr)
        {
            $offset++;
            $sql .= ", ifnull(" . $this->getSQLEvalExpr($expr) . ",0) as expr$offset ";
        }

        foreach($this->metadata as $expr)
        {
        	$offset++;
        	$sql .= ", ifnull(" . $this->getSQLEvalExpr($expr) . ",0) as expr$offset ";
        }

        $sql .=
            "\n" . 'FROM ' ."\n" .
            ' documents d ' ."\n";

        if ($this->used_tables['document_metadata_version'] > 0)
        {
        	$sql .= ' INNER JOIN document_metadata_version dmv ON d.metadata_version_id=dmv.id' . "\n";
        }
        if ($this->used_tables['document_content_version'] > 0)
        {
        	$sql .= ' INNER JOIN document_content_version dcv ON dmv.content_version_id=dcv.id ' . "\n";
        }
        if ($this->used_tables['document_fields_link'] > 0)
        {
        	$sql .= ' LEFT JOIN document_fields_link pdfl ON dmv.id=pdfl.metadata_version_id ' . "\n";
        }

        if ($this->used_tables['tag_words'] > 0)
        {
            $sql .= ' LEFT OUTER JOIN document_tags dt  ON dt.document_id=d.id ' . "\n" .
            		' LEFT OUTER JOIN tag_words tw  ON dt.tag_id = tw.id ' . "\n";
        }

		$offset = 0;
        foreach($this->db as $expr)
        {
        	$field       = $expr->left();
        	$jointable=$field->getJoinTable();
        	if (!is_null($jointable))
        	{
				$fieldname = $this->resolveTableToAlias($field->getTable()) . '.' . $field->getField();

    	        $joinalias = "$jointable$offset";
    	        $joinfield = $field->getJoinField();
				$sql .= " LEFT OUTER JOIN $jointable $joinalias ON $fieldname=$joinalias.$joinfield\n";
        	}
        	$offset++;
        }



        $offset=0;
        foreach($this->metadata as $expr)
        {
            $offset++;
            $field = $expr->left();

            $fieldid = $field->getFieldId();
            $sql .= " LEFT JOIN document_fields_link dfl$offset ON dfl$offset.metadata_version_id=d.metadata_version_id AND dfl$offset.document_field_id=$fieldid" . "\n";
            $sql .= " LEFT JOIN document_fields df$offset ON df$offset.id=dfl$offset.document_field_id" . "\n";
        }


        $sql .=
            'WHERE dmv.status_id=1 AND d.status_id=1 AND ' . "\n ";

       	return $sql;
	}

	private function resolveMetadataOffset($expr)
	{
		assert($expr->left()->isMetadataField() );

		$offset=0;
		foreach($this->metadata as $item)
		{
			if ($item->getExprId() == $expr->getExprId())
			{
				return $offset;
			}
			$offset++;
		}
		throw new Exception('metadata field not found');
	}

	private function resolveJoinOffset($expr)
	{


		$offset=0;
		foreach($this->db as $item)
		{
			if ($item->getExprId() == $expr->getExprId())
			{
				return $offset;
			}
			$offset++;
		}
		throw new Exception('join field not found');
	}

	private function buildCoreSQLExpr($expr)
	{
		$left = $expr->left();
        $right = $expr->right();
		if (DefaultOpCollection::isBoolean($expr))
		{
			$query = '(' . $this->buildCoreSQLExpr($left) . ' ' . $expr->op() . ' ' . $this->buildCoreSQLExpr($right)  . ')';
		}
		else
		{
			$query = $this->getSQLEvalExpr($expr);
		}

		if ($expr->not())
		{
			$query = "NOT $query";
		}

		return $query;
	}

	public function buildComplexQuery($expr)
	{
//		print "building complex \n\n";
		$this->exploreExprs($expr);

		$sql = $this->buildCoreSQL();

		$sql .= $this->buildCoreSQLExpr($expr);

		return $sql;
	}

	public function buildSimpleQuery($op, $group)
	{
//		print "building simple \n\n";
		$this->exploreGroup($group);

        $sql = $this->buildCoreSQL();

        $offset=0;
        foreach($this->db as $expr)
        {
            if ($offset++)
            {
                $sql .= " $op\n " ;
            }

			$field       = $expr->left();

			if (is_null($field->getJoinTable()))
				{
	        	    $alias      = $this->resolveTableToAlias($field->getTable());
    	        	$fieldname  = $alias . '.' . $field->getField();
				}
				else
				{
					$offset = $this->resolveJoinOffset($expr);
					$matching = $field->getMatchingField();
					$tablename = $field->getJoinTable();
					$fieldname = "$tablename$offset.$matching";
				}


            $value      = $expr->right();
            $sql .= $value->getSQL($field, $left->modifyName($fieldname), $expr->op(), $expr->not());
        }

        $moffset=0;
        foreach($this->metadata as $expr)
        {
            $moffset++;
            if ($offset++)
            {
                $sql .= " $op\n " ;
            }

            $field = $expr->left();
            $value = $expr->right();

            $sql .= $value->getSQL($field, "dfl$moffset.value", $expr->getOp());
        }

        return $sql;
	}

	public function getRanking($result)
	{
		$ranker = RankManager::get();
		$score = 0;
		foreach($result as $col=>$val)
		{
			if ($val + 0 == 0)
			{
				// we are not interested if the expression failed
				continue;
			}

			if (substr($col, 0, 4) == 'expr' && is_numeric(substr($col, 4)))
			{

				$exprno = substr($col, 4);
				if ($exprno <= count($this->db))
				{
					$expr = $this->db[$exprno-1];
					$left=$expr->left();
					$score += $ranker->scoreField($left->getTable(), 'T', $left->getField());
				}
				else
				{
					$exprno -= count($this->db);
					$expr = $this->metadata[$exprno-1];
					$left=$expr->left();
					$score += $ranker->scoreField($left->getTable(), 'M', $left->getField());
				}
			}
		}

		return $score;
	}

	public function getResultText($result)
	{
		$text = array();
		foreach($result as $col=>$val)
		{
			if (substr($col, 0, 4) == 'expr' && is_numeric(substr($col, 4)))
			{
				if ($val + 0 == 0)
				{
					// we are not interested if the expression failed
					continue;
				}
				$exprno = substr($col, 4);
				if ($exprno <= count($this->db))
				{
					$expr = $this->db[$exprno-1];
				}
				else
				{
					$exprno -= count($this->db);
					$expr = $this->metadata[$exprno-1];
				}
				$text[] = (string) $expr;
			}
		}
		return '(' . implode(') AND (', $text) . ')';
	}


}



class OpExpr extends Expr
{
    /**
     * The left side of the  expression
     *
     * @var Expr
     */
    protected $left_expr;

    /**
     * The operator on the left and right
     *
     * @var ExprOp
     */
    protected $op;
    /**
     * The right side of the expression
     *
     * @var Expr
     */
    protected $right_expr;

    /**
     * This indicates that the expression is negative
     *
     * @var boolean
     */
    protected $not;

    protected $point;

    protected $has_text;
    protected $has_db;

    private $debug = false;

//    protected $flattened;

    protected $results;

    public function setResults($results)
    {
        $this->results=$results;
    }
    public function getResults()
    {
        return $this->results;
    }

    public function setHasDb($value=true)
    {
        $this->has_db=$value;
    }

    public function setHasText($value=true)
    {
        $this->has_text=$value;
    }

    public function getHasDb()
    {
        return $this->has_db;
    }
    public function getHasText()
    {
        return $this->has_text;
    }
    public function setPoint($point)
    {
        $this->point = $point;
       /* if (!is_null($point))
        {
            $this->flattened = new FlattenedGroup($this);
        }
        else
        {
            if (!is_null($this->flattened))
            {
                unset($this->flattened);
            }
            $this->flattened = null;
        }*/
    }

    public function getPoint()
    {
        return $this->point;
    }

	public function hasSameOpAs($expr)
	{
		return $this->op() == $expr->op();
	}

	public static function rewriteString(&$left, &$op, &$right, $not=false)
    {
		if ($right->isValueExpr())
    	{
    		$value = $right->getValue();
    	}
    	else
    	{
    		$value = $right;
    	}

    	$text = array();


    	preg_match_all('/[\']([^\']*)[\']/',$value, $matches);

    	foreach($matches[0] as $item)
    	{
    		$text [] = $item;

    		$value = str_replace($item, '', $value);
    	}

    	$matches = explode(' ', $value);

    	foreach($matches as $item)
    	{
    		if (empty($item)) continue;
    		$text[] = $item;
    	}

    	if (count($text) == 1)
    	{
    		return;
    	}

    	$doctext = $left;

    	$left = new OpExpr($doctext, $op, new ValueExpr($text[0]));

    	for($i=1;$i<count($text);$i++)
    	{
    		if ($i==1)
    		{
    			$right = new OpExpr($doctext, $op, new ValueExpr($text[$i]));
    		}
    		else
    		{
    			$join = new OpExpr($doctext, $op, new ValueExpr($text[$i]));
    			$right = new OpExpr($join, ExprOp::OP_OR, $right);
    		}
    	}

    	$op = ExprOp::OP_OR;
    }


    /**
     * Constructor for the expression
     *
     * @param Expr $left
     * @param ExprOp $op
     * @param Expr $right
     */
    public function __construct($left, $op, $right, $not = false)
    {
    	// if left is a string, we assume we should convert it to a FieldExpr
        if (is_string($left))
        {
            $left = new $left;
	    }

        // if right is not an expression, we must convert it!
        if (!($right instanceof Expr))
        {
            $right = new ValueExpr($right);
        }

        if ($right->isValueListExpr())
        {
			$right->rewrite($left, $op, $right, $not);
        }
        else
        // rewriting is based on the FieldExpr, and can expand a simple expression
        // into something a little bigger.
		if ($left->isFieldExpr())
		{
			 $left->rewrite($left, $op, $right, $not);
		}

		// transformation is required to optimise the expression tree so that
		// the queries on the db and full text search are optimised.
		if (DefaultOpCollection::isBoolean($op))
		{
			$this->transform($left, $op, $right, $not);
		}

        parent::__construct();

        $left->setParent($this);
        $right->setParent($this);
        $this->left_expr=&$left;
        $this->op = $op;
        $this->right_expr=&$right;
        $this->not = $not;
        $this->has_text=false;

       // $this->setPoint('point');

        if ($left->isSearchableText())
        {
            $this->setHasText();
        }
        else if ($left->isDBExpr())
        {
            $this->setHasDb();
        }
		elseif ($left->isOpExpr())
        {
            if ($left->getHasText()) { $this->setHasText(); }
            if ($left->getHasDb())   { $this->setHasDb(); }
        }

        if ($right->isOpExpr())
        {
            if ($right->getHasText()) { $this->setHasText(); }
            if ($right->getHasDb())   { $this->setHasDb(); }
        }
     //   $this->flattened=null;

     	// $left_op, etc indicates that $left expression is a logical expression
        $left_op = ($left->isOpExpr() && DefaultOpCollection::isBoolean($left));
        $right_op = ($right->isOpExpr() && DefaultOpCollection::isBoolean($right));

		// check which trees match
        $left_op_match  = ($left_op  && $this->hasSameOpAs($left)) ;
        $right_op_match = ($right_op  && $this->hasSameOpAs($left)) ;

        $point = null;


        if ($left_op_match && $right_op_match) { $point = 'point'; }

		$left_op_match_flex  = $left_op_match || ($left->isOpExpr());
        $right_op_match_flex = $right_op_match || ($right->isOpExpr());

        if ($left_op_match_flex && $right_op_match_flex) { $point = 'point'; }

        if (!is_null($point))
        {
        	if ($left_op_match && $left->getPoint() == 'point')   { $left->setPoint(null); }
        	if ($right_op_match && $right->getPoint() == 'point') { $right->setPoint(null); }

        	if ($left->isMergePoint() && is_null($right->getPoint())) { $right->setPoint('point'); }
        	if ($right->isMergePoint() && is_null($left->getPoint())) { $left->setPoint('point'); }

        	if ($left->isMergePoint() || $right->isMergePoint())
        	{
        		$point = 'merge';

        		if (!$left->isMergePoint()) { $left->setPoint('point'); }
	        	if (!$right->isMergePoint()) { $right->setPoint('point'); }

	        	if ($this->isDBonly() || $this->isTextOnly())
        		{
					$this->clearPoint();
					$point = 'point';
        		}
        	}
        }

        if ($point == 'point')
        {
			if ($this->isDBandText())
			{
				$point = 'merge';
				$left->setPoint('point');
				$right->setPoint('point');
			}
        }
        if (is_null($point) && !DefaultOpCollection::isBoolean($op))
        {
        	$point = 'point';
        }

		$this->setPoint($point);
    }

    private function isDBonly()
    {
    	return $this->getHasDb() && !$this->getHasText();
    }

    private function isTextOnly()
    {
    	return !$this->getHasDb() && $this->getHasText();
    }

    private function isDBandText()
    {
    	return $this->getHasDb() && $this->getHasText();
    }

    /**
     * Enter description here...
     *
     * @param OpExpr $expr
     */
    protected  function clearPoint()
    {
    	if (DefaultOpCollection::isBoolean($this))
    	{
			$this->left()->clearPoint();
			$this->right()->clearPoint();
    	}
    	if ($this->isMergePoint())
    	{
    		$this->setPoint(null);
    	}
    }


    protected function isMergePoint()
    {
    	return in_array($this->getPoint(), array('merge','point'));
    }

    /**
     * Returns the operator on the expression
     *
     * @return ExprOp
     */
    public function op()
    {
        return $this->op;
    }

    /**
     * Returns true if the negative of the operator should be used in evaluation
     *
     * @param boolean $not
     * @return boolean
     */
    public function not($not=null)
    {
        if (!is_null($not))
        {
            $this->not = $not;
        }

        return $this->not;
    }

    /**
     * The left side of the  expression
     *
     * @return Expr
     */
    public function &left()
    {
        return $this->left_expr;
    }

    /**
     * The right side of the  expression
     *
     * @return Expr
     */
    public function &right()
    {
        return $this->right_expr;
    }

    /**
     * Converts the expression to a string
     *
     * @return string
     */
    public function __toString()
    {
        $expr = $this->left_expr . ' ' . $this->op .' ' .  $this->right_expr;

        if (is_null($this->parent))
        {
            return $expr;
        }

        if ($this->parent->isOpExpr())
        {
            if ($this->parent->op != $this->op && in_array($this->op, DefaultOpCollection::$boolean))
            {
                 $expr = "($expr)";
            }
        }

        if ($this->not())
        {
             $expr = "!($expr)";
        }

        return $expr;
    }

    /**
     * Is the expression valid
     *
     * @return boolean
     */
    public function is_valid()
    {
        $left = $this->left();
        $right = $this->right();
        return $left->is_valid() && $right->is_valid();
    }

    /**
     * Finds the results that are in both record sets.
     *
     * @param array $leftres
     * @param array $rightres
     * @return array
     */
	protected static function intersect($leftres, $rightres)
    {
    	if (empty($leftres) || empty($rightres))
    	{
    		return array(); // small optimisation
    	}
    	$result = array();
    	foreach($leftres as $item)
    	{
    		$document_id = $item->DocumentID;

    		if (!$item->IsLive)
    		{
    			continue;
    		}

    		if (array_key_exists($document_id, $rightres))
    		{
    			$check = $rightres[$document_id];

    			$result[$document_id] = ($item->Rank < $check->Rank)?$check:$item;
    		}
    	}
    	return $result;
    }

    /**
     * The objective of this function is to merge the results so that there is a union of the results,
     * but there should be no duplicates.
     *
     * @param array $leftres
     * @param array $rightres
     * @return array
     */
    protected static function union($leftres, $rightres)
    {
    	if (empty($leftres))
    	{
    		return $rightres; // small optimisation
    	}
    	if (empty($rightres))
    	{
    		return $leftres; // small optimisation
    	}
    	$result = array();

    	foreach($leftres as $item)
    	{
			if ($item->IsLive)
    		{
    			$result[$item->DocumentID] = $item;
    		}
    	}

    	foreach($rightres as $item)
    	{
    		if (!array_key_exists($item->DocumentID, $result) || $item->Rank > $result[$item->DocumentID]->Rank)
    		{
    			$result[$item->DocumentID] = $item;
    		}
    	}
    	return $result;
    }

    /**
     * Enter description here...
     *
     * @param OpExpr $left
     * @param ExprOp $op
     * @param OpExpr $right
     * @param boolean $not
     */
    public function transform(& $left, & $op, & $right, & $not)
    {

    	if (!$left->isOpExpr() || !$right->isOpExpr() || !DefaultOpCollection::isBoolean($op))
    	{
    		return;
    	}

		if ($left->isTextOnly() && $right->isDBonly())
		{
			// we just swap the items around, to ease other transformations
			$tmp = $left;
			$left = $right;
			$right = $tmp;
			return;
		}

		if ($op != $right->op() || !DefaultOpCollection::isBoolean($right))
		{
			return;
		}

		if ($op == ExprOp::OP_OR && ($not || $right->not()))
		{
			// NOTE: we can't transform. e.g.
			// db or !(db or txt) => db or !db and !txt
			// so nothing to do

			// BUT: db and !(db and txt) => db and !db and !txt
			return;
		}

		$rightLeft = $right->left();
		$rightRight = $right->right();

		if ($left->isDBonly() && $rightLeft->isDBonly())
		{
			$newLeft = new OpExpr( $left, $op, $rightLeft );

			$right = $rightRight;
			$left = $newLeft;
			return;
		}

		if ($left->isTextOnly() && $rightRight->isTextOnly())
		{
			$newRight = new OpExpr($left, $op, $rightRight);
			$left = $rightLeft;
			$right = $newRight;
			return;
		}

    }

    private function findDBNode($start, $op, $what)
    {
    	if ($start->op() != $op)
    	{
    		return null;
    	}
    	switch($what)
    	{
    		case 'db':
    			if ($start->isDBonly())
    			{
    				return $start;
    			}
    			break;
    		case 'txt':
    			if ($start->isTextOnly())
    			{
    				return $start;
    			}
    			break;
    	}
    	$node = $this->findDBNode($start->left(), $op, $what);
    	if (is_null($left))
    	{
    		$node = $this->findDBNode($start->right(), $op, $what);
    	}
    	return $node;

    }

    public function traverse($object, $method, $param)
    {
    	if ($this->isOpExpr())
    	{
	    	$object->$method($param);
    	}
    }

    private function exploreItem($item, & $group, $interest)
    {
    	if (($interest == 'db' && $item->getHasDb()) ||
    		($interest == 'text' && $item->getHasText()))
    	{
			if (in_array($item->op(), array(ExprOp::OP_OR, ExprOp::OP_AND)))
			{
				$this->exploreItem($item->left(),  $group, $interest);
				$this->exploreItem($item->right(),  $group, $interest);
			}
			else
			{
				$group[] = $item;
			}
    	}
    }

    private function explore($left, $right, & $group, $interest)
    {
		$this->exploreItem($left,  $group, $interest);
		$this->exploreItem($right,  $group, $interest);
    }

    private function exec_db_query($op, $group)
    {
    	if (empty($group)) { return array(); }

    	$exprbuilder = new SQLQueryBuilder();

    	if (count($group) == 1)
    	{
    		$sql = $exprbuilder->buildComplexQuery($group[0]);
    	}
    	else
    	{
			$sql = $exprbuilder->buildSimpleQuery($op, $group);
    	}

    	$results = array();

    	if ($this->debug) print "\n\n$sql\n\n";
    	$rs = DBUtil::getResultArray($sql);

    	if (PEAR::isError($rs))
    	{
    		throw new Exception($rs->getMessage());
    	}

    	foreach($rs as $item)
    	{
    		$document_id = $item['id'];
    		$rank = $exprbuilder->getRanking($item);
    		if (!array_key_exists($document_id, $results) || $rank > $results[$document_id]->Rank)
    		{
    			$results[$document_id] = new MatchResult($document_id, $rank, $item['title'], $exprbuilder->getResultText($item));
    		}
    	}

    	return $results;

    }

    private function exec_text_query($op, $group)
    {
    	if (empty($group)) { return array(); }

    	$exprbuilder = new TextQueryBuilder();

    	if (count($group) == 1)
    	{
    		$query = $exprbuilder->buildComplexQuery($group[0]);
    	}
    	else
    	{
			$query = $exprbuilder->buildSimpleQuery($op, $group);
    	}

    	$indexer = Indexer::get();
    	if ($this->debug) print "\n\n$query\n\n";
    	$results = $indexer->query($query);
    	foreach($results as $item)
    	{
    		$item->Rank = $exprbuilder->getRanking($item);
    		$exprbuilder->setQuery($query);
    		$item->Text = $exprbuilder->getResultText($item);
    	}

    	return $results;


    }

	public function evaluate()
	{
		$left = $this->left();
        $right = $this->right();
        $op = $this->op();
        $point = $this->getPoint();
        $result = array();
        if (empty($point))
        {
        	$point = 'point';
        }

		if ($point == 'merge')
		{

			$leftres = $left->evaluate();
			$rightres = $right->evaluate();
			switch ($op)
			{
				case ExprOp::OP_AND:
					if ($this->debug) print "\n\nmerge: intersect\n\n";
					$result = OpExpr::intersect($leftres, $rightres);
					break;
				case ExprOp::OP_OR:
					if ($this->debug) print "\n\nmerge: union\n\n";
					$result = OpExpr::union($leftres, $rightres);
					break;
				default:
					throw new Exception("this condition should not happen");
			}
		}
		elseif ($point == 'point')
		{
			if ($this->isDBonly())
			{
				$result = $this->exec_db_query($op, array($this));
			}
			elseif ($this->isTextOnly())
			{
				$result = $this->exec_text_query($op, array($this));
			}
			elseif (in_array($op, array(ExprOp::OP_OR, ExprOp::OP_AND)))
			{
				$db_group = array();
				$text_group = array();
				$this->explore($left, $right, $db_group, 'db');
				$this->explore($left, $right, $text_group, 'text');

				$db_result = $this->exec_db_query($op, $db_group);
				$text_result = $this->exec_text_query($op, $text_group);

				switch ($op)
				{
					case ExprOp::OP_AND:
						if ($this->debug) print "\n\npoint: intersect\n\n";
						$result = OpExpr::intersect($db_result, $text_result);
						break;
					case ExprOp::OP_OR:
						if ($this->debug) print "\n\nmerge: union\n\n";
						$result = OpExpr::union($db_result, $text_result);
						break;
					default:
						throw new Exception('how did this happen??');
				}
			}
			else
			{
				throw new Exception('and this?');
			}
		}
		else
		{
			// we don't have to do anything
			//throw new Exception('Is this reached ever?');
		}

		$permResults = array();
		foreach($result as $idx=>$item)
		{
			$doc = Document::get($item->DocumentID);
			if (Permission::userHasDocumentReadPermission($doc))
			{
				$permResults[$idx] = $item;
			}
		}

		return $permResults;
	}

    public function toViz(&$str, $phase)
    {
        $expr_id = $this->getExprId();
        $left = $this->left();
        $right = $this->right();
        $hastext = $this->getHasText()?'TEXT':'';
        $hasdb = $this->getHasDb()?'DB':'';
        switch ($phase)
        {
            case 0:
                $not = $this->not()?'NOT':'';
                $str .= "struct$expr_id [style=box, label=\"$expr_id: $not $this->op $this->point $hastext$hasdb\"]\n";
                break;
            case 1:
                $left_id = $left->getExprId();
                $str .= "struct$expr_id -> struct$left_id\n";
                $right_id = $right->getExprId();
                $str .= "struct$expr_id -> struct$right_id\n";
                break;
        }
        $left->toViz($str, $phase);
        $right->toViz($str, $phase);
    }

}




?>