# Saft

[![Coverage Status](https://coveralls.io/repos/github/SaftIng/Saft/badge.svg?branch=master)](https://coveralls.io/github/SaftIng/Saft)

| Component | Build Status                                                                                                              |
|:----------|:--------------------------------------------------------------------------------------------------------------------------|
| Data      | [![Build Status](https://travis-ci.org/SaftIng/Saft.data.svg?branch=master)](https://travis-ci.org/SaftIng/Saft.data)     |
| Rdf       | [![Build Status](https://travis-ci.org/SaftIng/Saft.rdf.svg?branch=master)](https://travis-ci.org/SaftIng/Saft.rdf)       |
| Sparql    | [![Build Status](https://travis-ci.org/SaftIng/Saft.sparql.svg?branch=master)](https://travis-ci.org/SaftIng/Saft.sparql) |
| Store     | [![Build Status](https://travis-ci.org/SaftIng/Saft.store.svg?branch=master)](https://travis-ci.org/SaftIng/Saft.store)   |

The Saft PHP framework provides RDF handling and support for Semantic Web technologies. It consists of the Saft.Library (_Saft.data_, _Saft.rdf_, _Saft.sparql_ and _Saft.store_), Saft.Additions (e.g. adapter for triple stores) and Saft.skeleton (to jump start your project).

## Documentation

For documentation please see http://safting.github.io/doc/.

## License

Copyright (C) 2017 by Konrad Abicht, Natanael Arndt and the individual [contributors](CONTRIBUTORS)

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, see <http://www.gnu.org/licenses>.
Please see [LICENSE](LICENSE) for further information.

## Current development status

Saft is under development but provides [stable releases](https://github.com/SaftIng/Saft/releases) already. Its used in different scenarios and is already performing very well for our approaches and rapid prototyping. Saft provides basic support for the following major Semantic Web libraries for PHP:
* Erfurt (currently only QueryCache)
* ARC2 (currently only data storage)
* EasyRDF (currently only parser and serializer)

Sure, there is still work to do to refine the library, so you are very welcome to join us and help to make Saft better!
