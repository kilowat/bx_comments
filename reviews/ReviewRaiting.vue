<template>
  <div class="rating">
    <ul class="list" :class="{ disabled: disabled }">
      <li
        :key="star"
        v-for="star in maxstars"
        @mouseover="hoverStar(star)"
        @mouseleave="mouseLeftStar"
        :class="[{ active: star <= stars }]"
        @click="rate(star)"
        class="star"
      >
        <svg class="star-icon filled" alt="star" v-if="star <= stars" width="20px" height="20px" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns">
          <!-- Generator: Sketch 3.0.3 (7891) - http://www.bohemiancoding.com/sketch -->
          <title>icon 23 star</title>
          <desc>Created with Sketch.</desc>
          <defs></defs>
          <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd" sketch:type="MSPage">
            <g id="icon-23-star" sketch:type="MSArtboardGroup" fill="#f12027">
              <polygon id="star" sketch:type="MSShapeGroup" points="16 22 7 28 11 18 2 12 12 12 16 2 20 12 30 12 21 18 25 28 "></polygon>
            </g>
          </g>
        </svg>
        <svg class="star-icon outline" alt="star" v-else  width="20px" height="20px" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns">
          <!-- Generator: Sketch 3.0.3 (7891) - http://www.bohemiancoding.com/sketch -->
          <title>icon 23 star</title>
          <desc>Created with Sketch.</desc>
          <defs></defs>
          <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd" sketch:type="MSPage">
            <g id="icon-23-star" sketch:type="MSArtboardGroup" fill="#c2c7c7">
              <polygon id="star" sketch:type="MSShapeGroup" points="16 22 7 28 11 18 2 12 12 12 16 2 20 12 30 12 21 18 25 28 "></polygon>
            </g>
          </g>
        </svg>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  components: {},
  data() {
    return {
      stars: this.star,
      star_desc: this.getRatingDesc(this.star),
    };
  },
  props: {
    star: {
      type: Number,
    },
    maxstars: {
      type: Number,
      default: 5,
    },
    hasresults: {
      type: Boolean,
      default: true,
    },
    hasdescription: {
      type: Boolean,
      default: true,
    },
    ratingdescription: {
      type: Array,
      default: () => {
        return [
          {
            text: "Poor",
            class: "star-poor",
          },
          {
            text: "Below Average",
            class: "star-belowAverage",
          },
          {
            text: "Average",
            class: "star-average",
          },
          {
            text: "Good",
            class: "star-good",
          },
          {
            text: "Excellent",
            class: "star-excellent",
          },
        ];
      },
    },
    starsize: {
      type: String,
      default: "2x",
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },
  methods: {
    rate(star) {
      if (this.disabled) {
        return;
      }
      if (star <= this.maxstars && star >= 0) {
        this.stars = this.stars === star ? star - 1 : star;
      }
      this.$emit('on-rate', this.stars);
    },
    hoverStar(star) {
      if (this.disabled) {
        return;
      }
      if (star <= this.maxstars && star >= 0) {
        this.star_desc = this.ratingdescription[star - 1];
      }
    },
    mouseLeftStar() {
      if (this.disabled) {
        return;
      }
      if (this.stars) {
        this.star_desc = this.ratingdescription[this.stars - 1];
        return this.star_desc;
      } else {
        this.star_desc = "";
      }
    },
    getRatingDesc(star) {
      if (star) {
        this.star_desc = this.ratingdescription[star - 1];
      }
      return this.star_desc;
    },
  },
};
</script>

<style lang="scss" scoped>
ul.list li,
span {
  display: inline-block;
  margin: 6px;
  height: 12px;
}
.list {
  margin: 0 0 0px 0;
  padding: 0;
  list-style-type: none;
  &:hover {
    .star {
    }
  }
  span {
    width: 130px;
    margin-left: 5px;
    padding: 5px;
    border-radius: 2px;
    font-size: 13px;
    text-align: center;
    font-weight: bold;
    transition: 0.2s;
    line-height: 25px;
  }
}
.list.disabled {
  &:hover {
    .star {
      color: black;
      cursor: default;
    }
    .star.active {
    }
  }
}
.star {
  cursor: pointer;
  &:hover {
    & ~ .star {
      &:not(.active) {
        color: inherit;
      }
    }
  }
}
.active {
}

</style>