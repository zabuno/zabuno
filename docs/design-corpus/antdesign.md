24/24 tamamlandı. Yaklaşım doğru ve mevcut Kernel mimarisiyle uyumlu. Ben bunu şu sınırla onaylarım:

> Ant Design, MetaFramer Kernel’in UI teknolojisi değil; standart yönetim ekranları için platform/SDK tarafında sunulan varsayılan ve değiştirilebilir React renderer’ıdır.

## Kısa durum

Ant Design somut bir React bileşen kütüphanesidir; headless değildir. Headless kalan şey `ArcheType + SurfaceContract + SDK` katmanıdır. [Ant Design’ın resmi tanımı da onu React tabanlı kurumsal UI kütüphanesi olarak konumlandırıyor.](https://ant.design/docs/react/introduce/?locale=en)

Mevcut mimariniz zaten bu ayrımı destekliyor:

- Kernel; kuralları, izinleri, aksiyonları ve ArcheType sözleşmesini taşır.
- Platform; generated SDK ve Surface projeksiyonlarını taşır.
- Surface UI kodu değil, renderer’ın okuyacağı sözleşmedir.
- Standart CRUD için `projected`, marka veya deneyime özel ekranlar için `custom` kullanılır.

Bunlar mevcut [Kernel README](~/DEV/mimari/metaframer-kernel/worktrees/kernel-runtime-substrate-s1-2026-08-06/README.md:28) ve [Surface sözleşmesi](~/DEV/mimari/actionplan/docs/surface-spec.md:15) ile doğrudan uyumlu.

## Olması gereken katmanlar

| Katman | Sorumluluk | Ant Design bağımlılığı |
|---|---|---|
| Kernel | ArcheType, izin, aksiyon, invariant, audit | Kesinlikle yok |
| Generated SDK | Tipli veri, sorgu ve komut istemcisi | Yok |
| SurfaceContract | Alan, kolon, aksiyon, layout, state, a11y | Yok |
| Renderer registry | Surface’i hangi UI’ın çizeceğini seçer | Burada olabilir |
| `react-antd` renderer | Form, liste, tablo, modal, filtre | Varsayılan |
| App/theme | Marka tasarımı veya tamamen custom frontend | Değiştirilebilir |

Akış şu olur:

```text
ArcheType + SurfaceContract
            ↓
       Generated SDK
            ↓
Renderer: react-antd (varsayılan)
            ↓
Anında çalışan Form / Liste / Tablo

Daha sonra:
Renderer: custom-react / vue / başka teknoloji
```

Örneğin geliştirici `Müşteri` ArcheType’ını tanımladığında:

- Ad, e-posta, durum alanları otomatik forma dönüşür.
- Liste kolonları, filtreler ve aksiyonlar otomatik Ant Design Table üzerinde görünür.
- Kaydetme, izin, audit ve validation hâlâ Kernel/SDK sözleşmesinden gelir.
- Daha sonra tasarım ekibi aynı veriyi tamamen özel bir müşteri portalında gösterebilir.
- Özel frontend yazmak Kernel davranışını veya veriyi değiştirmez.

Bu, Frappe’nin DocType’tan otomatik Form ve List View üretmesine oldukça benzer. Frappe de DocType için form ve liste görünümünü otomatik üretir, ardından JavaScript ile özelleştirme olanağı verir. [Frappe Form](https://docs.frappe.io/framework/user/en/tutorial/create-a-doctype), [Frappe List View](https://docs.frappe.io/framework/user/en/api/list).

## Neyi bozabilir?

Yanlış katmana yerleştirilirse şu şeyleri bozar:

1. **Kernel bağımsızlığını**

   Kernel paketlerinden herhangi biri `antd`, React, JSX, DOM tipi veya Ant Design token’ı import ederse Onion sınırı bozulur.

2. **Gerçek headless yapıyı**

   Surface içine `Form.Item`, `Table.Column`, `rowSelection`, `ConfigProvider` gibi Ant Design’a özgü alanlar yazılmamalı.

   Doğrusu:

   ```text
   field.kind = "email"
   field.required = true
   column.sortable = true
   action.kind = "submit"
   ```

   Bunların hangi Ant Design prop’una dönüşeceğine renderer karar vermeli.

3. **Form state ve doğrulama otoritesini**

   Mevcut Surface sözleşmesi form için React Hook Form + Zod öngörüyor. Ant Design Form da kendi state ve validation sistemine sahip. İkisini aynı anda otorite yaparsak çift doğrulama, farklı hata mesajları ve senkronizasyon sorunları çıkar.

   Önerim:

   - State ve şema doğrulama otoritesi: RHF + Zod
   - Görsel input, layout ve feedback bileşenleri: Ant Design
   - Ant Design Form’un bağımsız validation modeli: kapalı veya yalnız görsel adaptör

4. **Teknoloji değiştirme özgürlüğünü**

   Generated SDK `ReactNode`, JSX veya Ant Design tipi döndürürse Vue/Svelte/custom istemci fiilen imkânsızlaşır. SDK yalnız veri, tipli aksiyon ve state üretmeli.

5. **Marka bağımsızlığını**

   Kernel veya Surface içinde doğrudan Ant token adları tutulmamalı. Vendor-independent semantik token’lar tutulmalı:

   ```text
   color.primary
   color.danger
   spacing.compact
   radius.control
   density.table
   ```

   `react-antd` renderer bunları Ant Design token’larına çevirmeli. Ant Design tema sistemi `ConfigProvider`, global ve component token’larını destekliyor; dolayısıyla bu adaptasyon uygulanabilir. [Ant Design theme customization](https://ant.design/docs/react/customize-theme/?locale=en-US)

6. **Gelişmiş ekranları**

   Ant Design Table standart CRUD listeleri için uygundur. Ancak spreadsheet seviyesinde hücre düzenleme, büyük veri sanallaştırması, WebGL harita, storefront veya fabrika terminali gibi yüzeylere zorla uygulanmamalı.

   Mevcut karar korunmalı:

   - İç panel, CRUD, form, basit tablo: `projected` + Ant Design
   - Marka-özel portal, storefront, gerçek zamanlı ekran, gelişmiş data grid: `custom`

7. **Bağımsız sürümlemeyi**

   Ant Design major sürüm yükseltmesi Kernel major sürümü gerektirmemeli. Örneğin:

   ```text
   metaframer-kernel
   metaframer-sdk
   metaframer-surface-runtime
   metaframer-renderer-react-antd
   ```

   ayrı paketler ve ayrı SemVer yaşam döngüleri taşımalı.

Drupal benzetmesinde küçük bir düzeltme var: modern Drupal’ın çekirdek varsayılanı Bootstrap/jQuery değildir; Twig tabanlı tema sistemi ile Olivero/Claro gelir. Bootstrap ayrı bir contributed theme’dir. [Drupal theming](https://www.drupal.org/docs/develop/theming-drupal), [Olivero](https://www.drupal.org/docs/core-modules-and-themes/core-themes/olivero), [Drupal Bootstrap](https://www.drupal.org/project/bootstrap). Fakat “hemen çalışan sade varsayılan UI, sonra tema ile değiştirme” fikri açısından benzetmeniz doğrudur.

## Uygulanacak sıra

Mevcut teslim sırası bozulmamalı:

1. Kernel public contract’ları tamamlanır.
2. Generated SDK oluşturulur.
3. Vendor-neutral Surface runtime ve renderer registry gelir.
4. `react-antd` varsayılan Admin Surface renderer’ı geliştirilir.
5. Form/list/detail/table golden-slice uygulaması yapılır.
6. Custom renderer kaçış kapısı doğrulanır.
7. Tema/token adaptörü eklenir.

Yani Ant Design’ı şimdi Kernel’in içine sokmuyoruz; [Kernel → SDK → app-core → module → app](~/DEV/mimari/actionplan/docs/kernel-sdk-app-delivery-sequence.md:35) sırasında Surface/platform aşamasına yerleştiriyoruz.

## Sade ürün sonucu

- `once`: ArcheType tanımlansa bile geliştirici frontend yazmadan sonucu göremiyordu.
- `simdi`: Plan uygulanırsa standart form, liste ve tablo anında çalışan Ant Design görünümüyle açılacak.
- `fark`: Backend/ArcheType geliştirmesi görünür ve denenebilir hale gelecek.
- `kullaniciYolculugu`: Geliştirici alanları tanımlar → önizlemeyi açar → kayıt ekler → listede görür → izin ve doğrulamayı test eder → isterse özel frontend’e geçer.
- `kalanEngel`: Renderer registry, vendor-neutral component sözlüğü ve ilk golden slice henüz uygulanmış değil.
- `capability_delta`: Bu tur yalnız mimari karar değerlendirmesidir; çalışan yeni ürün yeteneği henüz eklenmedi.

## Rollback planı

Ant Design ayrı renderer paketi olarak tutulursa rollback basittir:

- Varsayılan renderer config’den değiştirilir.
- `react-antd` paketi kaldırılır veya önceki sürüme döndürülür.
- Kernel, SDK, ArcheType ve Surface verilerinde migration gerekmez.
- Kullanıcı verisi veya iş kuralları etkilenmez.

## MASTER Nihai Kararı

**Şartlı kabul — mimari yön doğru.**

Kanonik karar metnini şöyle sabitlemenizi öneriyorum:

> MetaFramer, vendor-neutral SurfaceContract üzerinden üretilen standart Admin Surface’ler için varsayılan projected renderer olarak React + Ant Design sunar. Ant Design, Kernel veya generated SDK bağımlılığı değildir; platform/app manifestinde değiştirilebilen ve bağımsız sürümlenen bir renderer profile’dır. Custom renderer’lar aynı izin, audit, action, i18n ve erişilebilirlik sözleşmelerini koruyarak UI teknolojisini ve tasarımı tamamen değiştirebilir.

Bu ifade onaylanırsa yaklaşım Frappe benzeri anlık geliştirme deneyimini verir; fakat MetaFramer’ı Ant Design’a kilitlemez.

### Ajan bazlı bulgular

- **MASTER:** Yaklaşımı yukarıdaki sınırlarla teknik olarak uygun buldu.
- **Claude worker/reviewer:** N/A; bu tur yalnız salt-okunur mimari değerlendirmeydi, kod veya kanonik karar yazılmadı.
- **Test:** Uygulama değişmediği için çalıştırılmadı; doğrulama mevcut sözleşmeler ve resmi teknoloji belgeleri üzerinden yapıldı.
