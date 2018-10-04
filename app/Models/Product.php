<?php

namespace App\Models;

use App\Exceptions\CouponCodeUnavailableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Exceptions\InvalidRequestException;
class Product extends Model
{
    const TYPE_NORMAL = 'normal';
    const TYPE_CROWDFUNDING = 'crowdfunding';
    const TYPE_SECKILL = 'seckill';

    public static $typeMap = [
        self::TYPE_NORMAL  => '普通商品',
        self::TYPE_CROWDFUNDING => '众筹商品',
        self::TYPE_SECKILL => '秒杀商品',
    ];

    protected $fillable = [
        'title', 'description', 'image', 'on_sale',
        'rating', 'sold_count', 'review_count', 'price','category_id','type','long_title'
    ];

    protected $casts = [
        'on_sale' => 'boolean', // on_sale 是一个布尔类型的字段
    ];

    //与众筹表的关联
    public function crowdfunding()
    {
        return $this->hasOne(CrowdfundingProduct::class);
    }

    //与秒杀商品的关联
    public function seckill()
    {
        return $this->hasOne(SeckillProduct::class);
    }

    // 与商品SKU关联
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }

    //与商品属性表关联
    public function pro_attr()
    {
        return $this->hasMany(ProductAttribute::class);
    }

    //与商品值表关联
    public function attr()
    {
        return $this->hasMany(Attribute::class);
    }

    //与分类表的关联
    public function category()
    {
        return $this->belongsTo(Category::class);
    }


    //补全商品图片url
    public function getFullImageAttribute()
    {
        // 如果 image 字段本身就已经是完整的 url 就直接返回
        if (Str::startsWith($this->attributes['image'], ['http://', 'https://'])) {
            return $this->attributes['image'];
        }
        return \Storage::disk('public')->url($this->attributes['image']);
    }

    //获取这个商品下的属性以及属性值（可选属性可唯一属性都找出来）
    public function getPropertiesAttribute()
    {
        //所有的属性（包括可选和唯一）
        ProductAttribute::where('product_id', $this->attributes['id'])->get();

    }

    //获取商品详情页面的SKU以及可选属性和唯一属性
    public function getSkuDetail()
    {
        //现有的sku
        $skus = $this->skus;
        //唯一属性
        $unique_attr = $this->pro_attr()->where('hasMany', '0')->get();
        //可选属性
        $select_attr = $this->pro_attr()->with('attribute')
            ->where('hasMany', '1')->get()->toArray();
        if (!count($skus))  throw new CouponCodeUnavailableException('该商品没有库存啦');
        return [
            'select_attr'=>$select_attr,
            'skus' => $skus,
            'unique_attr' =>$unique_attr
        ];
    }

    //获取商品评价，用于详情页面的展示
    public function getReview()
    {
        return OrderItem::query()
            ->with(['order.user'])
            ->where('product_id', $this->id)
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at', 'desc')
            ->limit(10)->get();
    }

    //将商品信息转换成ElasticSearch的存储数组
    public function toESArray()
    {
        // 只取出需要的字段
        $arr = array_only($this->toArray(), [
            'id',
            'type',
            'title',
            'category_id',
            'long_title',
            'on_sale',
            'rating',
            'sold_count',
            'review_count',
            'price',
        ]);

        // 如果商品有类目，则 category 字段为类目名数组，否则为空字符串
        $arr['category'] = $this->category ? explode('-', $this->category->full_name) : '';
        // 类目的 path 字段
        $arr['category_path'] = $this->category ? $this->category->path : '';
        // strip_tags 函数可以将 html 标签去除
        $arr['description'] = strip_tags($this->description);
        // 只取出需要的 SKU 字段
        $arr['skus'] = $this->skus->map(function (ProductSku $sku) {
            return array_only($sku->toArray(), ['title', 'description', 'price']);
        });
        // 只取出需要的商品属性字段
        $arr['properties'] = $this->getProperties();

        return $arr;
    }

    //获取这个商品下的所有属性名=>属性值（包括可选和唯一两种属性）
    public function getProperties()
    {
        //商品属性有两种，
        //唯一，可以直接从属性表中的val字段拿到属性值
        //可选，属性表只记录了属性名，属性值存在Attrubutes表中
        $property_arr = [];
        foreach ($this->pro_attr as $k => $attr) {
            if ($attr->hasmany == 0) {
                //唯一属性
                $property_arr[] = ['name' => $attr->name, 'value' => $attr->val];
            } else {
                //可选属性
                $select_arr = Attribute::where('attr_id', $attr->id)->get(); //所有的可选属性值
                foreach ($select_arr as $select) {
                    $property_arr[] = ['name' => $attr->name, 'value' => $select->attr_val];
                }
            }
        }
        return $property_arr;
    }

}
