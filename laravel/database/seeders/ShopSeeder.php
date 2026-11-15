<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\ShopCategory;
use App\Models\ShopProduct;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        if (! Setting::read('site')) {
            Setting::write('site', [
                'name' => 'Handmade',
                'description' => 'Украшения из бисера и вязаные игрушки ручной работы.',
                'phone' => '+7 (900) 123-45-67',
                'email' => 'hello@handmade.ru',
                'address' => 'Москва, ул. Лавандовая, 7',
                'workingHoursText' => 'Ежедневно 10:00–20:00',
                'socials' => [
                    'vk' => 'https://vk.com/handmade',
                    'telegram' => 'https://t.me/handmade',
                    'instagram' => 'https://instagram.com/handmade',
                ],
                'seo' => [
                    'title' => 'Handmade — украшения и игрушки ручной работы',
                    'description' => 'Украшения из бисера и вязаные игрушки ручной работы. Каждое изделие — частичка тепла и вдохновения.',
                ],
            ]);
        }

        if (! Setting::read('shop')) {
            Setting::write('shop', [
                'deliveryFee' => 39000,
                'freeFrom' => 300000,
                'pickupAddress' => 'Москва, ул. Лавандовая, 7',
                'deliveryNote' => '2–5 дней',
                'currency' => 'RUB',
            ]);
        }

        if (ShopCategory::query()->exists()) {
            return;
        }

        $categories = [
            [
                'name' => 'Украшения из бисера',
                'slug' => 'bead',
                'title' => 'Украшения из бисера',
                'subtitle' => 'Браслеты, серьги и колье из бисера и натуральных камней',
                'short_name' => 'Украшения из бисера',
                'sort' => 0,
            ],
            [
                'name' => 'Вязаные игрушки',
                'slug' => 'toy',
                'title' => 'Вязаные игрушки',
                'subtitle' => 'Амигуруми из мягкой пряжи - с любовью и вниманием к деталям',
                'short_name' => 'Вязаные игрушки',
                'sort' => 1,
            ],
            [
                'name' => 'Подарки и сувениры',
                'slug' => 'gift',
                'title' => 'Подарки и сувениры',
                'subtitle' => 'Готовые подарки и упаковка в лавандовой гамме',
                'short_name' => 'Подарки и сувениры',
                'sort' => 2,
            ],
        ];

        foreach ($categories as $category) {
            ShopCategory::query()->create($category);
        }

        $bySlug = ShopCategory::query()->pluck('id', 'slug');

        $products = [
            [
                'slug' => 'bracelet-lavender',
                'category' => 'bead',
                'name' => 'Браслет из бисера «Лаванда»',
                'price' => 69000,
                'badge' => 'Хит',
                'short_description' => 'Нежный браслет из бисера и натуральных камней в лавандовой гамме - лёгкий акцент на каждый день.',
                'description' => 'Браслет набран вручную из матового бисера и полированных бусин авантюрина. Мягкая лавандовая гамма подходит и к повседневным образам, и к вечернему платью. Каждая бусина закреплена отдельно, поэтому браслет держит форму и не растягивается.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'бисер, авантюрин, фурнитура'],
                    ['name' => 'Размер', 'value' => '17 см (под заказ - любой)'],
                    ['name' => 'Изготовление', 'value' => '2-3 дня'],
                    ['name' => 'Упаковка', 'value' => 'подарочная коробочка'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1629890731335-52295b8be1d9?auto=format&fit=crop&w=1000&q=85',
                'tone' => 'photo',
                'zoom' => null,
                'is_featured' => true,
                'sort' => 0,
            ],
            [
                'slug' => 'earrings-pearl',
                'category' => 'bead',
                'name' => 'Серьги «Ягодки»',
                'price' => 79000,
                'badge' => null,
                'short_description' => 'Серьги из бисера в виде маленьких ягод - красный, зелёный и жемчужный бисер на золотистых крючках.',
                'description' => 'Каждая ягодка сплетена вручную из мелкого бисера: красные бусины, зелёные листики и жемчужные акценты. Серьги почти ничего не весят, красиво двигаются и подходят даже тем, кто обычно не носит крупные украшения. Фурнитура гипоаллергенная.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'бисер, жемчужные бусины, позолоченная фурнитура'],
                    ['name' => 'Размер', 'value' => 'около 2,5 см'],
                    ['name' => 'Изготовление', 'value' => '2-3 дня'],
                    ['name' => 'Упаковка', 'value' => 'подарочная коробочка'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1653083210739-7cc5cf95f6ed?auto=format&fit=crop&w=1000&q=85',
                'tone' => 'photo',
                'zoom' => 1.6,
                'is_featured' => true,
                'sort' => 1,
            ],
            [
                'slug' => 'necklace-lace',
                'category' => 'bead',
                'name' => 'Колье «Кружево»',
                'price' => 129000,
                'badge' => 'Новинка',
                'short_description' => 'Кружевное колье из мелкого бисера - воздушное, как тонкое кружево, и удивительно лёгкое.',
                'description' => 'Колье сплетено из матового и жемчужного бисера в технике кружевного плетения. Ложится мягкой волной, не тянет шею и красиво смотрится и с открытым платьем, и поверх топа. Застёжка регулируется по длине.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'бисер, жемчужные бусины'],
                    ['name' => 'Размер', 'value' => '38-42 см с регулировкой'],
                    ['name' => 'Изготовление', 'value' => '5-7 дней'],
                    ['name' => 'Упаковка', 'value' => 'подарочная коробочка'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1786118791837-1a2f74beabf6?auto=format&fit=crop&w=1000&q=85',
                'tone' => 'photo',
                'zoom' => null,
                'is_featured' => false,
                'sort' => 2,
            ],
            [
                'slug' => 'necklace-tenderness',
                'category' => 'bead',
                'name' => 'Колье «Нежность»',
                'price' => 89000,
                'badge' => null,
                'short_description' => 'Колье из бисера с голубыми и золотистыми акцентами - тонкая ручная работа на каждый день.',
                'description' => 'Каждая бусина нанизана вручную: голубой, белый и золотистый бисер собраны в лёгкий узор. Колье застёгивается на карабин с регулировкой длины, поэтому подойдёт и под горловину, и под глубокий вырез.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'бисер, стеклярус, фурнитура'],
                    ['name' => 'Размер', 'value' => '42-46 см'],
                    ['name' => 'Изготовление', 'value' => '4-5 дней'],
                    ['name' => 'Упаковка', 'value' => 'подарочная коробочка'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1786052338161-bc8eba2d9f3e?auto=format&fit=crop&w=1000&q=85',
                'tone' => 'photo',
                'zoom' => null,
                'is_featured' => false,
                'sort' => 3,
            ],
            [
                'slug' => 'bunny-amigurumi',
                'category' => 'toy',
                'name' => 'Зайка амигуруми',
                'price' => 149000,
                'badge' => 'Хит',
                'short_description' => 'Вязаная зайка из мягкой пряжи - уютный подарок для детей и взрослых.',
                'description' => 'Зайка связана крючком из хлопковой пряжи, набита гипоаллергенным холлофайбером. Глазки вышиты, поэтому игрушка безопасна для малышей. Высота - 22 см, лапки и ушки держат форму.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'хлопковая пряжа, холлофайбер'],
                    ['name' => 'Высота', 'value' => '22 см'],
                    ['name' => 'Изготовление', 'value' => '5-7 дней'],
                    ['name' => 'Уход', 'value' => 'ручная стирка при 30°'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1755315897817-97bd6ec34256?auto=format&fit=crop&w=1000&q=85',
                'tone' => '',
                'zoom' => null,
                'is_featured' => true,
                'sort' => 0,
            ],
            [
                'slug' => 'cat-scarf',
                'category' => 'toy',
                'name' => 'Котик в шарфе',
                'price' => 139000,
                'badge' => null,
                'short_description' => 'Амигуруми-котик с вязаным шарфом и маленьким бантом на ушке.',
                'description' => 'Котик связан вручную из мягкой пряжи, шарф снимается - можно менять настроение игрушки. Мордочка вышита, все детали закреплены на совесть. Отличный подарок на новоселье или день рождения.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'пряжа, холлофайбер, атласная лента'],
                    ['name' => 'Высота', 'value' => '20 см'],
                    ['name' => 'Изготовление', 'value' => '5-7 дней'],
                    ['name' => 'Уход', 'value' => 'ручная стирка при 30°'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1615486363990-dff6c0722a38?auto=format&fit=crop&w=1000&q=85',
                'tone' => '',
                'zoom' => null,
                'is_featured' => true,
                'sort' => 1,
            ],
            [
                'slug' => 'bear-hat',
                'category' => 'toy',
                'name' => 'Мишка в шапочке',
                'price' => 169000,
                'badge' => 'Новинка',
                'short_description' => 'Вязаный мишка в комбинезоне и шапочке - коллекционная игрушка ручной работы.',
                'description' => 'Мишка связан из пряжи двух оттенков, одежда съёмная. Лапки на шарнирах, поэтому игрушка сидит и стоит самостоятельно. Каждая деталь вывязана вручную, повтор исключён.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'пряжа, холлофайбер, пуговицы'],
                    ['name' => 'Высота', 'value' => '24 см'],
                    ['name' => 'Изготовление', 'value' => '7-10 дней'],
                    ['name' => 'Уход', 'value' => 'сухая чистка'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1615486364134-62a4c72c822d?auto=format&fit=crop&w=1000&q=85',
                'tone' => '',
                'zoom' => null,
                'is_featured' => false,
                'sort' => 2,
            ],
            [
                'slug' => 'bear-lavender',
                'category' => 'toy',
                'name' => 'Мишка «Лаванда»',
                'price' => 159000,
                'badge' => null,
                'short_description' => 'Классический вязаный мишка в лавандовом цвете - тёплый и очень обнимательный.',
                'description' => 'Мягкий мишка связан из плюшевой пряжи, внутри - холлофайбер. Лапы и голова подвижные, поэтому игрушку удобно сажать и укладывать. Прекрасно смотрится на полке и в детской кроватке.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'плюшевая пряжа, холлофайбер'],
                    ['name' => 'Высота', 'value' => '26 см'],
                    ['name' => 'Изготовление', 'value' => '6-8 дней'],
                    ['name' => 'Уход', 'value' => 'ручная стирка при 30°'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1577635515158-dcce4789c8fb?auto=format&fit=crop&w=1000&q=85',
                'tone' => '',
                'zoom' => null,
                'is_featured' => false,
                'sort' => 3,
            ],
            [
                'slug' => 'gift-box',
                'category' => 'gift',
                'name' => 'Подарочная коробка «Лаванда»',
                'price' => 59000,
                'badge' => null,
                'short_description' => 'Коробка с атласной лентой и сухоцветами - для подарка, который хочется сохранить.',
                'description' => 'Плотная коробка пастельного оттенка с широкой атласной лентой и веточкой сухоцвета. Внутри - мягкая стружка, которая бережно держит украшение или игрушку. Можно добавить открытку с вашим текстом.',
                'specs' => [
                    ['name' => 'Материалы', 'value' => 'картон, атлас, сухоцветы'],
                    ['name' => 'Размер', 'value' => '18 × 13 × 8 см'],
                    ['name' => 'Открытка', 'value' => 'в подарок'],
                    ['name' => 'Упаковка', 'value' => 'готова к вручению'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?auto=format&fit=crop&w=1000&q=85',
                'tone' => 'neutral',
                'zoom' => null,
                'is_featured' => false,
                'sort' => 0,
            ],
            [
                'slug' => 'gift-set',
                'category' => 'gift',
                'name' => 'Набор «Нежность»',
                'price' => 199000,
                'badge' => 'Выгодно',
                'short_description' => 'Браслет и серьги из бисера в подарочной упаковке - готовый подарок без хлопот.',
                'description' => 'В набор входят браслет «Лаванда», серьги «Цветочки» и открытка с вашим пожеланием. Всё уложено в подарочную коробку с лентой - можно сразу вручать. Приятнее, чем покупать по отдельности.',
                'specs' => [
                    ['name' => 'Состав', 'value' => 'браслет, серьги, открытка'],
                    ['name' => 'Упаковка', 'value' => 'подарочная коробка'],
                    ['name' => 'Изготовление', 'value' => '3-5 дней'],
                    ['name' => 'Открытка', 'value' => 'с вашим текстом'],
                ],
                'image_url' => 'https://images.unsplash.com/photo-1787074630731-d048e8455ee2?auto=format&fit=crop&w=1000&q=85',
                'tone' => '',
                'zoom' => null,
                'is_featured' => false,
                'sort' => 1,
            ],
        ];

        foreach ($products as $product) {
            $categorySlug = $product['category'];
            unset($product['category']);
            $product['category_id'] = $bySlug[$categorySlug];
            ShopProduct::query()->create($product);
        }
    }
}
