//include gulp
let gulp = require("gulp");
let rename = require("gulp-rename");
let sass = require("gulp-sass");
let uglify = require('gulp-uglify');
let concat = require('gulp-concat');

//Scripts
gulp.task('scripts', function() {
	gulp.src([
		'./src/scripts/site.js'
	])
	.pipe(concat({
		path: 'site.js'
	}))
	.pipe(gulp.dest('./web/js/'))
	.pipe(uglify())
	.pipe(rename({
		suffix: '.min'
	}))
	.pipe(gulp.dest('./web/js/'));
});

//SASS Compile
gulp.task('styles', function() {
    gulp.src(['./src/styles/site.scss'])
		.pipe(sass())
		.pipe(concat('site.css'))
        .pipe(gulp.dest('./web/css/'))
        .pipe(sass({outputStyle: 'compressed'}))
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest('./web/css/'));
});

//watch files
gulp.task('watch', function(){
	gulp.start('styles');
	gulp.start('scripts');
	gulp.watch('./src/styles/*.scss', ['styles']);
	gulp.watch('./src/scripts/*.js', ['scripts']);
});

//default tasks
gulp.task('default', ['scripts', 'styles']);
