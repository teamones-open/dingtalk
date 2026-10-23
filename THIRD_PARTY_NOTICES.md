# Upstream sources and modifications

## EasyDingTalk

- Source: https://github.com/mingyoung/dingtalk
- Baseline: tag `2.3.1`, commit `74d12baff001494985e6f7c2fba875702c6a9535`.
- License: MIT; original license is preserved in `LICENSE` and source headers.
- This is a Teamones compatibility fork, not the upstream unfinished 3.x branch.

## Internal HTTP compatibility layer

- Source: https://github.com/overtrue/http
- Baseline: tag `1.1.3`, commit `6374e7486d637cd7f8dfa2b8a2f4d5ab055110b4`.
- Author: overtrue / anzhengchao@gmail.com; Composer metadata declares MIT.
- Original copyright headers are retained. Permission and warranty terms are in
  `licenses/overtrue-http-MIT.txt`.
- Files are maintained under `EasyDingTalk\Internal\Http`, not `Overtrue\Http`.
- Changes: PHP 8.5 interface/nullability/serialization compatibility, Guzzle 7 /
  PSR-7 2 compatibility, safe XML helper, multipart resource handling.

The public `EasyDingTalk` business clients preserve the 2.3.1 endpoints and
argument shapes. The config/collection helper concrete class now belongs to
the SDK-private namespace; consumers explicitly type-hinting Overtrue classes
must adapt. Do not install this package alongside `mingyoung/dingtalk`.
