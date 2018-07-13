all: fresh build install

composer:
	composer update

fresh:
	echo fresh

install: 
	echo install
	
build:
	echo build

pretest:
	composer --ansi --no-interaction update
	php -f tests/PrepareForTest.php

incoming:
	cd src &&  php -f ParujPrijateFaktury.php && cd ..
outcoming:
	cd src &&  php -f ParujVydaneFaktury.php && cd ..
newtoold:
	cd src &&  php -f ParujFakturyNew2Old.php && cd ..
parujnew2old:
	cd src &&  php -f ParujFakturyNew2Old.php && cd ..

match: incoming outcoming parujnew2old
test: pretest match

clean:
	rm -rf debian/php-flexibee-matcher 
	rm -rf debian/*.substvars debian/*.log debian/*.debhelper debian/files debian/debhelper-build-stamp
	rm -rf vendor composer.lock

deb:
	dch -i
	dpkg-buildpackage -A -us -uc

.PHONY : install
	