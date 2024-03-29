<?php
namespace App\Libs\Utils;

use DB;

// App or Class using traits may override these
defined('PSGC_UTILS_MODEL_UNIQUE_SLUG_ATTEMPTS') or define('PSGC_UTILS_MODEL_UNIQUE_SLUG_ATTEMPTS', 20); // set default
defined('PSGC_UTILS_MODEL_UNIQUE_SLUG_RAND_MAX') or define('PSGC_UTILS_MODEL_UNIQUE_SLUG_RAND_MAX', 9999); // set default

trait ModelTraits
{

    public static function getTableName()
    {
        return with(new static)->getTable();
    }

    public static function getGuardedColumns()
    {
        return with(new static)->guarded;
    }

    public static function _getFillables()
    {
        $table = self::getTableName();
        $columns = \Schema::getColumnListing($table);
        $guarded = self::getGuardedColumns();
        $fillables = array_diff($columns,$guarded);
        return $fillables;
    }

    // %FIXME: better implementation
    // SEE: https://stackoverflow.com/questions/32989034/laravel-handle-findorfail-on-fail
    // Like Eloquent's first(), but specific to where-by-slug, and throws detailed exception
    public static function findBySlug($slug)
    {
        //return with(new static)->getTable();
        //$record = \App\Models\Scheduleditem::where('slug',$slug)->first();
        $record = self::where('slug',$slug)->first();
        if ( empty($record) ) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Could not find record with slug '.$slug);
        }
        return $record;
    }

    // %FIXME: better implementation
    public static function findByPKID($pkid)
    {
        //return with(new static)->getTable();
        //$record = \App\Models\Scheduleditem::where('slug',$slug)->first();
        $record = self::find($pkid);
        if ( empty($record) ) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Could not find record with pkid '.$pkid);
        }
        return $record;
    }

    // common baseline code 
    protected static function base_renderFieldKey(string $key) : string
    {
        $key = trim($key);
        switch ($key) {
            case 'guid':
                $key = 'GUID';
                break;
            case 'id':
                $key = 'PKID';
                break;
            case 'created_at':
                $key = 'Created';
                break;
            case 'updated_at':
                $key = 'Updated';
                break;
            default:
                // try to capture 'boolean' fields that start with 'is'
                $key = ucwords(preg_replace('/^is_/', 'Is ', $key));
                //$key = ucwords($key);
        }
        return $key;
    }

    public static function _renderFieldKey(string $key) : string
    {
        return self::base_renderFieldKey($key);
    }

    // child classes can override, but impl should call parent
    public function renderFieldKey(string $key) : string
    {
        return static::_renderFieldKey($key);  // %NOTE: late static binding
    }


    // child classes can override, but impl should call parent
    public function renderField(string $field) : ?string
    {
        $key = trim($field);
        switch ($key) {
            case 'guid':
                return strtoupper($this->{$field});
            case 'created_at':
            case 'updated_at':
            case 'deleted_at':
                return ViewHelpers::makeNiceDate($this->{$field},1,1); // number format, include time
            default:
                return $this->{$field};
        }
    }

    // $sluggableFields is an Array of table field names to use to create the slug
    public function slugify(Array $sluggableFields, String $slugField='slug', Bool $makeUnique=true) : string
    {
        $tablename = self::getTablename();

        // Get actual contents of the sluggable fields...
        $sluggable = [];
        foreach ($sluggableFields as $f) {
            $sluggable[] = $this->{$f};
        }
        $dbc = $this->getConnectionName();
        return  self::slugifyByTable($tablename, $sluggable, $slugField, $makeUnique, $dbc);
    }

    // $sluggable is an Array of strings, ints, values, etc  used to create the slug
    // Example usage of dynamic DB connection:
    //    $obj = new Content();
    //    $obj->setDBConnection($language->slug);
    //    $obj->fill($attrs);
    //    $obj->save();
    public static function slugifyByTable(String $table, Array $sluggable, String $slugField='slug', Bool $makeUnique=true, $dbconnection=null) : string
    {
        $slug = implode('-',$sluggable);
        $slug = preg_replace('~[^\\pL\d]+~u', '-', $slug); // replace non letter or digits by -
        $slug = trim($slug, '-'); // trim
        //$slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug); // transliterate
        $slug = strtolower($slug); // lowercase
        $slug = preg_replace('~[^-\w]+~', '', $slug); // remove unwanted characters

        if ($makeUnique) {
    
            $ogSlug = $slug;
            if (0) {
                if ( empty($dbconnection) ) {
                    $numMatches = DB::table($table)->where($slugField, '=', $slug)->count();
                } else {
                    $numMatches = DB::connection($dbconnection)->table($table)->where($slugField, '=', $slug)->count();
                }
                $slug = $ogSlug.'-'.$numMatches;
            } else {
                $iter = 0;
                do {
                    if ( empty($dbconnection) ) {
                        $numMatches = DB::table($table)->where($slugField, '=', $slug)->count();
                    } else {
                        $numMatches = DB::connection($dbconnection)->table($table)->where($slugField, '=', $slug)->count();
                    }
                    if ( 0 == $numMatches )  {
                        break; // already unique
                    }
                    if ( $iter++ > PSGC_UTILS_MODEL_UNIQUE_SLUG_ATTEMPTS ) {
                        throw new \Exception('Exceeded maximum number of attempts to create unique slug for field '.$slugField);
                    }
                    $slug = $ogSlug.'-'.rand(1, PSGC_UTILS_MODEL_UNIQUE_SLUG_RAND_MAX);
                } while ($numMatches > 0);
            }
        }

        return $slug;

    } // slugifyByTable()

    
    // Is a form field required?
    //   ~ assumes arrray static::$vrules
    public static function isFieldRequired(string $fieldKey) : bool
    {
        $isRequired = false; // default
        if ( array_key_exists($fieldKey, static::$vrules) ) {
            $isRequired = ( false !== strpos(static::$vrules[$fieldKey],'required') );
        }
        //dd('here',static::$vrules);
        return $isRequired;
    }

    // Render a form field label
    //    NOTE: $display is optional...if null Form::label() will fill in a sensible default
    public static function renderFormLabel(string $fieldKey, string $display=null, array $attrs=[]) : string
    {
        $html = '';
        $html .= \Form::label($fieldKey,$display,$attrs); // %TODO: default or render function for display text
        if ( self::isFieldRequired($fieldKey) ) {
            $html .= '<span class="tag-required">*</span>';
        }
        return $html;
    } // renderFormLabel()


}
